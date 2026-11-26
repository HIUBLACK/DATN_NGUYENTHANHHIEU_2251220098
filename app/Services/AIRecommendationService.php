<?php

namespace App\Services;

use App\Enums\ResumeParsingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\AIRecommendJobsJob;
use App\Models\CandidateProfile;
use App\Models\RecommendationItem;
use App\Models\RecommendationRun;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AIRecommendationService
{
    public const MESSAGES = [
        'no_resume' => 'Hãy tải CV và chọn CV chính để nhận đề xuất.',
        'cv_pending' => 'CV chính đang chờ phân tích AI.',
        'cv_failed' => 'Phân tích CV chưa thành công. Hãy thử lại tại trang quản lý CV.',
        'pending' => 'Đề xuất đã được xếp hàng, vui lòng chờ.',
        'processing' => 'AI đang tính điểm đề xuất việc làm.',
        'failed' => 'Chưa thể hoàn thành đề xuất. Bạn có thể thử lại.',
        'unavailable' => 'Dịch vụ AI tạm thời chưa sẵn sàng.',
        'stale' => 'Dữ liệu đã thay đổi, cần tính lại đề xuất.',
    ];

    public function __construct(
        private PublicJobService $publicJobs,
        private AIMatchingService $matching,
        private AISemanticSimilarityService $semantic
    ) {}

    /** Không gọi embedding trong HTTP; chỉ đối chiếu dữ liệu và đưa run mới vào queue. */
    public function page(User $actor, array $filters = [], bool $retry = false): array
    {
        $context = $this->context($actor);
        $data = [
            'recommendationState' => $context['state'],
            'recommendationMessage' => self::MESSAGES[$context['state']] ?? '',
            'recommendationResume' => $context['resume']?->display_name ?? null,
            'recommendations' => collect(),
        ];
        if ($context['state'] !== 'ready') {
            return $data;
        }
        $run = $this->ensureRun($actor, $context, $retry);
        $data['recommendationState'] = $run->status->value;
        if ($run->stale_at) {
            $data['recommendationState'] = 'stale';
        }
        if ($data['recommendationState'] !== 'completed') {
            $data['recommendationMessage'] = self::MESSAGES[$data['recommendationState']] ?? '';

            return $data;
        }
        // Luôn kiểm tra lại quyền công khai và published pointer, kể cả item từng đạt điểm cao.
        $publicIds = $this->publicJobs->search($filters)->select('jobs.id');
        $data['recommendations'] = $run->items()->with(['job.company', 'job.publishedVersion.skillRequirements'])
            ->whereIn('job_id', $publicIds)
            ->whereHas('job', fn ($query) => $query->whereColumn('jobs.published_version_id', 'recommendation_items.job_version_id'))
            ->orderByDesc('score')->orderBy('job_id')
            ->paginate(max(1, min(20, (int) config('ai.recommendation.page_size'))))->withQueryString()
            ->through(fn ($item) => [
                'job' => $this->publicJobs->card($item->job), 'score' => $item->score,
                'semantic_score' => $item->semantic_score, 'skill_score' => $item->skill_score,
                'matched' => array_column($item->matched_skills, 'name'),
                'missing' => array_column($item->missing_skills, 'name'), 'explanation' => $item->explanation,
            ]);

        return $data;
    }

    /** Context gồm pool tin và danh mục: tin mới, phân tích lại, đổi CV hoặc model đều tạo khóa mới. */
    private function context(User $actor): array
    {
        $user = User::find($actor->id);
        if (! $user || $user->role !== UserRole::Candidate || $user->status !== UserStatus::Active) {
            throw new AuthorizationException('Chỉ ứng viên đang hoạt động được xem đề xuất của mình.');
        }
        $resume = Resume::forCandidate($user->id)->where('is_primary', true)->first();
        $revision = $resume?->currentRevision()->first();
        $context = ['state' => 'no_resume', 'resume' => $resume];
        if (! $revision) {
            return $context;
        }
        if ($revision->parsing_status !== ResumeParsingStatus::Completed || ! is_array($revision->analysis_result)) {
            $context['state'] = $revision->parsing_status === ResumeParsingStatus::Failed ? 'cv_failed' : 'cv_pending';

            return $context;
        }
        try {
            if (! config('ai.embedding.enabled')) {
                throw new RuntimeException('embedding_disabled');
            }
            $digest = $this->semantic->modelDigest();
        } catch (Throwable) {
            $context['state'] = 'unavailable';

            return $context;
        }
        $jobs = $this->publicJobs->query()->orderByDesc('published_at')->orderByDesc('id')
            ->limit(max(1, min(20, (int) config('ai.recommendation.max_jobs'))))->get();
        $generation = (int) CandidateProfile::where('user_id', $user->id)->value('recommendation_generation');
        $pool = $jobs->map(fn ($job) => [
            $job->id, $job->published_version_id, $job->publishedVersion->getAttributes(),
            $job->publishedVersion->skillRequirements->toArray(),
        ])->all();
        $context += [
            'revision' => $revision, 'generation' => $generation,
            'targets' => $jobs->map(fn ($job) => [$job->id, $job->published_version_id])->all(),
            'model' => config('ai.embedding.model').'@'.$digest.'/'.config('ai.embedding.preprocessing_version'),
        ];
        $context['key'] = hash('sha256', json_encode([
            $user->id, $resume->id, $resume->updated_at, $revision->getAttributes(), $revision->skills()->orderBy('skill_id')->get()->toArray(),
            $generation, $pool, config('job-catalog'), config('ai.matching'), config('ai.embedding'),
            config('ai.recommendation.max_jobs'), $digest,
        ], JSON_THROW_ON_ERROR));
        $context['state'] = 'ready';

        return $context;
    }

    /** Khóa user + UNIQUE request_key chống reload/POST đồng thời tạo run trùng. */
    private function ensureRun(User $actor, array $context, bool $retry): RecommendationRun
    {
        return DB::transaction(function () use ($actor, $context, $retry) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            RecommendationRun::where('candidate_id', $actor->id)->where('request_key', '<>', $context['key'])
                ->whereNull('stale_at')->update(['stale_at' => now()]);
            $run = RecommendationRun::where('candidate_id', $actor->id)
                ->where('request_key', $context['key'])->lockForUpdate()->first();
            if ($run) {
                $expired = ($run->status->value !== 'completed' || $run->stale_at !== null)
                    && ($run->lease_expires_at === null || $run->lease_expires_at->isPast())
                    && $run->updated_at->lt(now()->subSeconds(max(60, (int) config('ai.recommendation.retry_cooldown'))));
                if (! $retry || ! $expired) {
                    return $run;
                }
                $run->items()->delete();
            } else {
                $run = new RecommendationRun;
                $run->forceFill([
                    'candidate_id' => $actor->id, 'resume_id' => $context['resume']->id,
                    'resume_revision' => $context['revision']->revision,
                    'recommendation_generation' => $context['generation'], 'model_version' => $context['model'],
                    'formula_version' => config('ai.matching.formula_version'), 'request_key' => $context['key'],
                ]);
            }
            $run->forceFill([
                'status' => 'pending', 'processing_token' => (string) Str::uuid(), 'attempt_count' => 0,
                'started_at' => null, 'completed_at' => null, 'stale_at' => null, 'error_code' => null,
                'error_message' => null, 'lease_expires_at' => null, 'next_retry_at' => null,
            ])->save();
            AIRecommendJobsJob::dispatch($run->id, $run->processing_token, $context['targets'], 0)->afterCommit();

            return $run;
        });
    }

    /** Mỗi queue job tính một tin; item tạm không được hiển thị cho tới khi run completed. */
    public function process(int $id, string $token, array $targets, int $index): string
    {
        $run = DB::transaction(function () use ($id, $token) {
            $run = RecommendationRun::lockForUpdate()->find($id);
            if (! $run || $run->stale_at || $run->processing_token !== $token || in_array($run->status->value, ['completed', 'failed'])) {
                return null;
            }
            if ($run->lease_expires_at?->isFuture()) {
                throw new RuntimeException('recommendation_busy');
            }
            $run->forceFill([
                'status' => 'processing', 'started_at' => $run->started_at ?? now(),
                'attempt_count' => $run->attempt_count + 1, 'lease_expires_at' => now()->addSeconds(260),
                'next_retry_at' => null,
            ])->save();

            return $run;
        });
        if (! $run) {
            return 'obsolete';
        }
        try {
            $actor = User::findOrFail($run->candidate_id);
            $context = $this->context($actor);
            if ($context['state'] === 'unavailable') {
                throw new RuntimeException('embedding_unavailable');
            }
            if (($context['key'] ?? null) !== $run->request_key || $context['targets'] !== $targets) {
                $this->failRun($id, $token, 'stale_inputs', true);

                return 'stale';
            }
            $result = isset($targets[$index]) ? $this->matching->forCandidate($actor, $targets[$index][0]) : null;
            if ($result && $result['status'] === 'insufficient_data'
                && array_diff($result['missing_data'], ['completed_job_analysis', 'embedding_input_missing']) !== []) {
                $this->failRun($id, $token, 'matching_data_missing');

                return 'failed';
            }
            if ($result && $result['status'] === 'unavailable') {
                if (in_array($result['error_code'] ?? '', [
                    'embedding_invalid_vectors', 'embedding_dimension_mismatch', 'embedding_zero_vector', 'invalid_local_endpoint',
                    'embedding_input_too_long',
                ], true)) {
                    $this->failRun($id, $token, $result['error_code']);

                    return 'failed';
                }
                throw new RuntimeException($result['error_code'] ?? 'embedding_unavailable');
            }
            if ($result && $result['status'] === 'completed') {
                foreach (['score', 'semantic_score', 'skill_score'] as $field) {
                    if (! is_numeric($result[$field]) || ! is_finite((float) $result[$field])
                        || $result[$field] < 0 || $result[$field] > 100) {
                        throw new RuntimeException('invalid_matching_score');
                    }
                }
                if ($result['model_version'] !== $run->model_version
                    || $result['job_version_id'] !== $targets[$index][1]
                    || $result['resume_id'] !== $run->resume_id || $result['resume_revision'] !== $run->resume_revision) {
                    $this->failRun($id, $token, 'stale_inputs', true);

                    return 'stale';
                }
            }
            // Kiểm tra lại sau HTTP rồi lưu trong transaction; run cũ không phục vụ khi context đã đổi.
            $latest = $this->context($actor);
            if ($latest['state'] === 'unavailable') {
                throw new RuntimeException('embedding_unavailable');
            }
            if (($latest['key'] ?? null) !== $run->request_key) {
                $this->failRun($id, $token, 'stale_inputs', true);

                return 'stale';
            }

            return $this->saveStep($run, $token, $targets, $index, $result);
        } catch (AuthorizationException) {
            $this->failRun($id, $token, 'candidate_unavailable', true);

            return 'stale';
        } catch (Throwable $exception) {
            // Chỉ lưu mã an toàn; không ghi raw response, SQL bindings hoặc nội dung CV.
            $allowed = ['embedding_unavailable', 'embedding_request_failed', 'embedding_model_unavailable',
                'embedding_model_changed', 'embedding_disabled', 'invalid_matching_score'];
            $code = in_array($exception->getMessage(), $allowed, true)
                ? $exception->getMessage() : 'matching_unavailable';
            \Illuminate\Support\Facades\Log::warning('Recommendation processing failed.', [
                'run_id' => $id, 'job_version_id' => $targets[$index][1] ?? null,
                'error_code' => $code, 'exception_class' => get_class($exception),
            ]);
            RecommendationRun::whereKey($id)->where('processing_token', $token)->update([
                'lease_expires_at' => null, 'next_retry_at' => now()->addSeconds(30), 'error_code' => $code,
            ]);
            throw new RuntimeException('Recommendation temporarily unavailable.');
        }
    }

    private function saveStep(RecommendationRun $run, string $token, array $targets, int $index, ?array $result): string
    {
        return DB::transaction(function () use ($run, $token, $targets, $index, $result) {
            $current = RecommendationRun::lockForUpdate()->findOrFail($run->id);
            if ($current->stale_at || $current->processing_token !== $token || $current->status->value !== 'processing') {
                return 'obsolete';
            }
            if (($result['status'] ?? null) === 'completed') {
                (new RecommendationItem)->forceFill([
                    'recommendation_run_id' => $run->id, 'job_id' => $targets[$index][0],
                    'job_version_id' => $result['job_version_id'], 'score' => $result['score'],
                    'semantic_score' => $result['semantic_score'], 'skill_score' => $result['skill_score'],
                    'matched_skills' => $result['matched_skills'], 'missing_skills' => $result['missing_skills'],
                    'explanation' => implode("\n", $result['reasons']),
                ])->save();
            }
            // Tin thiếu kết quả phân tích được bỏ qua có chủ đích, không tạo item điểm 0.
            $finished = $index + 1 >= count($targets);
            $current->forceFill([
                'status' => $finished ? 'completed' : 'processing',
                'completed_at' => $finished ? now() : null, 'processing_token' => (string) Str::uuid(),
                'lease_expires_at' => null, 'next_retry_at' => null, 'error_code' => null,
                'attempt_count' => 0,
            ])->save();
            if (! $finished) {
                AIRecommendJobsJob::dispatch($run->id, $current->processing_token, $targets, $index + 1)->afterCommit();
            }

            return $finished ? 'completed' : 'next';
        });
    }

    /** failed() và retry chỉ được sửa run khi token còn hiệu lực. */
    public function failRun(int $id, string $token, string $code = 'queue_failed', bool $stale = false): void
    {
        if ($code === 'queue_failed') {
            $code = RecommendationRun::whereKey($id)->where('processing_token', $token)->value('error_code') ?: $code;
        }
        RecommendationRun::whereKey($id)->where('processing_token', $token)
            ->whereIn('status', ['pending', 'processing'])->update([
                'status' => 'failed', 'error_code' => $code, 'lease_expires_at' => null,
                'next_retry_at' => null, 'stale_at' => $stale ? now() : null,
            ]);
    }
}
