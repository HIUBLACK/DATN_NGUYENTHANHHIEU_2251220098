<?php

namespace App\Services;

use App\Enums\ResumeParsingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use App\Support\JobCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Matching chỉ đọc dữ liệu đã phân tích; chưa ghi Recommendation hoặc đánh giá ứng tuyển. */
class AIMatchingService
{
    public function __construct(
        private PublicJobService $publicJobs,
        private AISemanticSimilarityService $semantic
    ) {}

    /** Chọn CV chính của chính actor và published version, không nhận revision tùy ý từ client. */
    public function forCandidate(User $actor, int $jobId): array
    {
        $result = $this->candidateInputs($actor, $jobId);
        $texts = $result['_texts'] ?? null;
        unset($result['_texts']);
        if ($texts === null) {
            return $result;
        }
        try {
            // HTTP chạy ngoài transaction; không giữ snapshot database trong lúc chờ model.
            $semantic = $this->semantic->compare($texts['resume'], $texts['job'], [
                $actor->id, $result['resume_revision_id'], $result['job_version_id'], $result['input_fingerprint'],
            ]);
        } catch (\Throwable $exception) {
            $result['status'] = 'unavailable';
            $result['missing_data'][] = 'semantic_embedding';
            $result['reasons'][] = 'Embedding chưa sẵn sàng hoặc không trả vector hợp lệ. Có thể thử lại sau.';
            // Chỉ trả mã cố định; không lộ request, response hoặc văn bản CV qua exception.
            $allowed = ['embedding_disabled', 'embedding_input_missing', 'embedding_input_too_long', 'embedding_request_failed',
                'embedding_invalid_vectors', 'embedding_dimension_mismatch', 'embedding_model_changed',
                'embedding_model_unavailable', 'embedding_zero_vector', 'invalid_local_endpoint'];
            $result['error_code'] = in_array($exception->getMessage(), $allowed, true)
                ? $exception->getMessage() : 'embedding_unavailable';
            if ($result['error_code'] === 'embedding_input_missing') {
                $result['status'] = 'insufficient_data';
            }

            return $result;
        }
        // Đọc mới sau HTTP: thay CV/chuyển published version/khóa tài khoản không nhận kết quả cũ.
        $current = $this->candidateInputs($actor, $jobId);
        unset($current['_texts']);
        if ($current['input_fingerprint'] !== $result['input_fingerprint']) {
            $current['status'] = 'insufficient_data';
            $current['missing_data'][] = 'stale_inputs';
            $current['reasons'][] = 'Dữ liệu vừa thay đổi trong lúc Matching; hãy tính lại.';

            return $current;
        }
        $result['semantic_score'] = $semantic['semantic_score'];
        $result['score'] = $this->totalScore($result['semantic_score'], $result['skill_score']);
        $result['model_version'] = $semantic['model_version'];
        $result['status'] = 'completed';
        $result['reasons'][] = 'Điểm ngữ nghĩa tính từ cosine của vector embedding; điểm tổng gồm 60% ngữ nghĩa và 40% kỹ năng.';

        return $result;
    }

    private function candidateInputs(User $actor, int $jobId): array
    {
        return DB::transaction(function () use ($actor, $jobId) {
            $candidate = User::find($actor->id);
            if (! $candidate || $candidate->role !== UserRole::Candidate || $candidate->status !== UserStatus::Active) {
                throw new AuthorizationException('Chỉ ứng viên đang hoạt động được sử dụng Matching.');
            }

            $result = $this->emptyResult($candidate->id, $jobId);
            $job = $this->publicJobs->query()->find($jobId);
            if (! $job) {
                $result['status'] = 'unavailable';
                $result['missing_data'][] = 'public_job';
                $result['reasons'][] = 'Tin không còn đủ điều kiện công khai và nhận hồ sơ.';

                return $result;
            }

            $version = $job->publishedVersion;
            $result['job_version_id'] = $version->id;
            $resume = Resume::forCandidate($candidate->id)->where('is_primary', true)->first();
            $revision = $resume?->currentRevision()->first();
            $result['resume_id'] = $resume?->id;
            $result['resume_revision_id'] = $revision?->id;
            $result['resume_revision'] = $revision?->revision;
            if (! $revision) {
                $result['missing_data'][] = 'primary_resume_revision';
                $result['reasons'][] = 'Chưa có CV chính và revision hiện hành.';

                return $result;
            }

            $resumeSkills = $revision->skills()->orderBy('skill_id')->get();
            $jobSkills = $version->skillRequirements->sortBy('skill_id')->values();
            $result['analysis_versions'] = [
                'resume' => $revision->parser_version,
                'job' => $version->ai_model_version,
            ];
            // Hash gồm cả lần phân tích và generation: đổi CV chính rồi đổi lại vẫn nhận diện kết quả cũ.
            $result['input_fingerprint'] = hash('sha256', json_encode([
                'candidate' => $candidate->id,
                'generation' => CandidateProfile::where('user_id', $candidate->id)->value('recommendation_generation'),
                'resume' => [$resume->id, $resume->updated_at, $revision->getAttributes()],
                'job' => [$job->id, $job->published_version_id, $version->getAttributes()],
                'resume_skills' => $resumeSkills->toArray(),
                'job_skills' => $jobSkills->toArray(),
                'catalog' => config('job-catalog.skills'),
                'formula' => config('ai.matching'),
                'embedding' => config('ai.embedding'),
            ], JSON_THROW_ON_ERROR));

            if ($revision->parsing_status !== ResumeParsingStatus::Completed
                || ! $this->hasAnalysis($revision->analysis_result, array_keys(AIOllamaService::SECTIONS))) {
                $result['missing_data'][] = 'completed_resume_analysis';
            }
            if ($version->ai_analysis_status !== 'completed'
                || ! $this->hasAnalysis($version->ai_analysis_result, array_keys(AIOllamaService::JOB_SECTIONS))) {
                $result['missing_data'][] = 'completed_job_analysis';
            }
            if ($result['missing_data']) {
                $result['reasons'][] = 'Cần kết quả phân tích CV và tin hợp lệ trước khi so khớp.';

                return $result;
            }

            $claims = collect(AIOllamaService::SECTIONS)->keys()
                ->sum(fn ($section) => count($revision->analysis_result[$section]));
            if ($claims === 0) {
                $result['missing_data'][] = 'resume_evidence';
                $result['reasons'][] = 'Phân tích CV không có thông tin có bằng chứng để đối chiếu.';

                return $result;
            }

            // Pivot của Recruiter là nguồn kỹ năng bắt buộc; AI không được ghi đè yêu cầu này.
            $skills = $this->matchSkills(
                $resumeSkills->pluck('skill_id')->all(),
                $jobSkills->where('is_required', true)->pluck('skill_id')->all()
            );
            $result = array_replace($result, $skills);
            if ($skills['missing_data']) {
                return $result;
            }
            $privateNames = [$candidate->name, $candidate->email, $job->company->name];
            $result['_texts'] = $this->embeddingTexts(
                $revision->analysis_result, $version->ai_analysis_result,
                $resumeSkills->pluck('skill_id')->all(), $jobSkills->pluck('skill_id')->all(), $privateNames
            );
            $result['reasons'][] = 'Điểm phù hợp không phải xác suất trúng tuyển.';

            return $result;
        });
    }

    /**
     * Hai luồng dùng cùng thứ tự mã kỹ năng trước khi tạo embedding.
     * Thứ tự từ pivot hoặc JobCatalog không được làm đổi điểm khi nguồn tương đương.
     * Không sắp lại claim hay bổ sung kỹ năng: dữ liệu lịch sử khác vẫn được giữ riêng.
     */
    public function embeddingTexts(
        array $resumeAnalysis,
        array $jobAnalysis,
        array $resumeSkillIds,
        array $jobSkillIds,
        array $privateNames = []
    ): array {
        $skillNames = function (array $ids): array {
            $ids = array_values(array_unique($ids));
            sort($ids, SORT_NUMERIC);

            return collect($ids)->map(fn ($id) => JobCatalog::find('skills', $id)?->name)->filter()->values()->all();
        };

        return [
            'resume' => $this->semantic->prepareText($resumeAnalysis, 'resume', $skillNames($resumeSkillIds), $privateNames),
            'job' => $this->semantic->prepareText($jobAnalysis, 'job', $skillNames($jobSkillIds), $privateNames),
        ];
    }

    /** Hàm thuần dùng lại cho snapshot sau này; đầu vào phải là mã kỹ năng đã có căn cứ. */
    public function matchSkills(array $resumeSkillIds, array $requiredSkillIds): array
    {
        $known = JobCatalog::all('skills')->keyBy('id');
        $resumeIds = array_values(array_unique($resumeSkillIds));
        $requiredIds = array_values(array_unique($requiredSkillIds));
        sort($resumeIds);
        sort($requiredIds);
        $result = ['skill_score' => null, 'matched_skills' => [], 'missing_skills' => [],
            'missing_data' => [], 'reasons' => []];
        foreach (array_merge($resumeIds, $requiredIds) as $id) {
            if (! is_int($id) || ! $known->has($id)) {
                $result['missing_data'][] = 'invalid_skill_catalog_code';
                $result['reasons'][] = 'Có mã kỹ năng không còn hợp lệ; không bỏ qua để làm sai mẫu số.';

                return $result;
            }
        }
        foreach ($requiredIds as $id) {
            $key = in_array($id, $resumeIds, true) ? 'matched_skills' : 'missing_skills';
            $result[$key][] = ['skill_id' => $id, 'name' => $known[$id]->name];
        }
        // Giữ độ chính xác đến bước tính tổng; chỉ tổng cuối cùng mới làm tròn 2 chữ số.
        $result['skill_score'] = $requiredIds ? 100 * count($result['matched_skills']) / count($requiredIds) : 0.0;
        $result['reasons'][] = $requiredIds
            ? 'Tìm thấy '.count($result['matched_skills']).'/'.count($requiredIds).' kỹ năng bắt buộc trong kết quả CV.'
            : 'Tin không có kỹ năng bắt buộc: điểm kỹ năng bằng 0 theo công thức v1.';
        $result['reasons'][] = 'Kỹ năng chưa tìm thấy nghĩa là chưa có bằng chứng trong CV, không khẳng định ứng viên không có.';

        return $result;
    }

    /** Chỉ dùng khi có hai điểm thật; NULL không được biến thành điểm 0. */
    public function totalScore(?float $semanticScore, ?float $skillScore): ?float
    {
        foreach ([$semanticScore, $skillScore] as $score) {
            if ($score !== null && (! is_finite($score) || $score < 0 || $score > 100)) {
                throw new InvalidArgumentException('Điểm thành phần phải nằm trong khoảng 0–100.');
            }
        }
        if ($semanticScore === null || $skillScore === null) {
            return null;
        }

        return round(
            config('ai.matching.semantic_weight') * $semanticScore
            + config('ai.matching.skill_weight') * $skillScore,
            2
        );
    }

    /** Kiểm tra lại trước khi dùng kết quả; không phục vụ cache của CV hoặc published version cũ. */
    public function isStale(User $actor, array $previous): bool
    {
        if (($previous['candidate_id'] ?? null) !== $actor->id) {
            throw new AuthorizationException('Không được dùng kết quả Matching của ứng viên khác.');
        }
        if (empty($previous['job_id']) || empty($previous['input_fingerprint'])) {
            return true;
        }
        $current = $this->forCandidate($actor, (int) $previous['job_id']);

        return $current['input_fingerprint'] !== $previous['input_fingerprint']
            || $current['model_version'] !== ($previous['model_version'] ?? null)
            || $current['status'] !== ($previous['status'] ?? null)
            || $current['missing_data'] !== ($previous['missing_data'] ?? null);
    }

    public function hasAnalysis(mixed $analysis, array $sections): bool
    {
        if (! is_array($analysis)) {
            return false;
        }
        foreach ($sections as $section) {
            if (! isset($analysis[$section]) || ! is_array($analysis[$section]) || ! array_is_list($analysis[$section])) {
                return false;
            }
            foreach ($analysis[$section] as $claim) {
                if (! is_array($claim) || ! is_string($claim['text'] ?? null)
                    || ! is_string($claim['evidence'] ?? null)
                    || trim($claim['text']) === '' || trim($claim['evidence']) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    private function emptyResult(int $candidateId, int $jobId): array
    {
        return [
            'status' => 'insufficient_data', 'score' => null, 'semantic_score' => null, 'skill_score' => null,
            'matched_skills' => [], 'missing_skills' => [], 'reasons' => [], 'missing_data' => [],
            'candidate_id' => $candidateId, 'resume_id' => null, 'resume_revision_id' => null,
            'resume_revision' => null, 'job_id' => $jobId, 'job_version_id' => null,
            'model_version' => null, 'formula_version' => config('ai.matching.formula_version'),
            'analysis_versions' => [], 'input_fingerprint' => null,
        ];
    }
}
