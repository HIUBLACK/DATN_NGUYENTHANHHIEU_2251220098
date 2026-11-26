<?php

namespace App\Services;

use App\Jobs\AIEvaluateApplicationJob;
use App\Models\Application;
use App\Models\ApplicationAiEvaluation;
use App\Models\User;
use App\Support\JobCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AIApplicationEvaluationService
{
    public function __construct(
        private AIMatchingService $matching,
        private AISemanticSimilarityService $semantic,
        private AIResumeAnalysisService $resumeAnalysis,
        private AIOllamaService $ollama
    ) {}

    /** Chỉ Recruiter sở hữu được yêu cầu; không phụ thuộc trạng thái tuyển dụng cuối cùng. */
    public function request(User $actor, string $publicId): ApplicationAiEvaluation
    {
        $application = app(RecruiterApplicationService::class)->owned($actor)->where('public_id', $publicId)->firstOrFail();
        Gate::forUser($actor)->authorize('viewNotes', $application);
        Gate::forUser($actor)->authorize('viewSnapshot', $application);
        try {
            $digest = $this->digest();
        } catch (Throwable) {
            throw ValidationException::withMessages(['ai' => 'Dịch vụ AI chưa sẵn sàng. Hãy thử lại sau.']);
        }

        return DB::transaction(function () use ($actor, $application, $digest) {
            $application = Application::lockForUpdate()->findOrFail($application->id);
            Gate::forUser($actor->fresh())->authorize('viewNotes', $application);
            $identity = $this->identity($application, $digest);
            $evaluation = $application->aiEvaluations()->where('model_version', $identity)
                ->where('job_version_id', $application->job_version_id)
                ->where('formula_version', config('ai.matching.formula_version'))->lockForUpdate()->first();
            if ($evaluation) {
                if ($evaluation->evaluation_status->value === 'completed'
                    || $evaluation->evaluation_status->value === 'pending'
                    || $evaluation->lease_expires_at?->isFuture()
                    || $evaluation->updated_at->gt(now()->subMinute())) {
                    return $evaluation;
                }
            } else {
                $evaluation = new ApplicationAiEvaluation;
                $evaluation->forceFill([
                    'application_id' => $application->id, 'job_id' => $application->job_id,
                    'job_version_id' => $application->job_version_id,
                    'resume_snapshot_hash' => $application->resume_snapshot_hash,
                    'model_version' => $identity, 'formula_version' => config('ai.matching.formula_version'),
                ]);
            }
            $evaluation->forceFill([
                'evaluation_status' => 'pending', 'processing_token' => (string) Str::uuid(),
                'attempt_count' => 0, 'error_code' => null, 'error_message' => null,
                'started_at' => null, 'lease_expires_at' => null, 'next_retry_at' => null,
            ])->save();
            DB::afterCommit(function () use ($evaluation, $actor) {
                try {
                    AIEvaluateApplicationJob::dispatch($evaluation->id, $evaluation->processing_token, $actor->id);
                } catch (Throwable) {
                    // Queue lỗi sau commit: giữ hồ sơ, cho phép yêu cầu lại; không để pending vô hạn.
                    $this->queueFailed($evaluation->id, $evaluation->processing_token);
                }
            });

            return $evaluation;
        });
    }

    /** Định danh <=100 ký tự chứa hash của model, thuật toán và nguồn lịch sử; không dùng CV chính. */
    private function identity(Application $application, string $digest): string
    {
        $version = $application->jobVersionQuery()->with('skillRequirements')->firstOrFail();
        $revision = $application->resumeRevisionQuery()->first();

        return 'application-v1:'.hash('sha256', json_encode([
            $application->candidate_id, $application->resume_id, $application->resume_revision,
            $application->resume_snapshot_hash, $application->resume_snapshot_path, $application->resume_snapshot_size,
            $revision?->file_hash, $revision?->analysis_result, $revision?->parser_version, $revision?->parsing_status,
            $version->only(array_merge($version->getFillable(), [
                'id', 'job_id', 'industry_id', 'job_specialization_id', 'job_level_id', 'job_position_id',
                'ai_analysis_status', 'ai_analysis_result', 'ai_model_version',
            ])),
            $version->skillRequirements->map(fn ($skill) => [$skill->skill_id, $skill->is_required])->sort()->values()->all(),
            $application->candidate->name, $application->candidate->email, $application->job->company->name,
            $digest, config('ai.model'), config('ai.max_characters'), config('ai.context'), config('ai.output_tokens'),
            config('ai.embedding'), config('ai.matching'), config('job-catalog.skills'),
        ], JSON_THROW_ON_ERROR));
    }

    private function digest(): string
    {
        if (! config('ai.embedding.enabled')) {
            throw new RuntimeException('embedding_disabled');
        }

        return $this->semantic->modelDigest();
    }

    /** Lọc/sắp xếp theo lần đánh giá đã lưu gần nhất; khi hiển thị vẫn kiểm tra stale riêng. */
    public function filterList(Builder $query, string $status, string $sort): void
    {
        $latest = ApplicationAiEvaluation::select('id')
            ->whereColumn('application_id', 'applications.id')->orderByDesc('id')->limit(1);
        if ($status === 'not_evaluated') {
            $query->whereDoesntHave('aiEvaluations');
        } elseif (in_array($status, ['pending', 'processing', 'completed', 'failed'], true)) {
            $query->whereHas('aiEvaluations', fn ($evaluation) => $evaluation
                ->where('id', $latest)->where('evaluation_status', $status));
        }
        if ($sort === 'score') {
            $score = ApplicationAiEvaluation::select('score')
                ->whereColumn('application_id', 'applications.id')
                ->where('evaluation_status', 'completed')->where('id', $latest)->limit(1);
            $query->orderByDesc($score);
        }
    }

    /** Hiển thị chỉ kiểm tra metadata, không gọi Qwen/embedding; unavailable là trạng thái UI, không thêm enum DB. */
    public function summaries(User $actor, iterable $applications): array
    {
        $applications = collect($applications instanceof AbstractPaginator
            ? $applications->items() : $applications);
        $hasEvaluations = ApplicationAiEvaluation::whereIn('application_id', $applications->pluck('id'))->exists();
        try {
            $digest = $hasEvaluations ? $this->digest() : null;
        } catch (Throwable) {
            $digest = null;
        }
        $results = [];
        foreach ($applications as $application) {
            Gate::forUser($actor)->authorize('viewNotes', $application);
            $evaluation = $application->aiEvaluations()->latest('id')->first();
            if ($evaluation && $digest !== null) {
                $current = $application->aiEvaluations()->where('model_version', $this->identity($application, $digest))
                    ->where('formula_version', config('ai.matching.formula_version'))->first();
                $evaluation = $current ?? $evaluation;
            }
            $state = $evaluation?->evaluation_status->value ?? 'not_evaluated';
            if ($evaluation && $digest === null) {
                $state = 'unavailable';
            } elseif ($evaluation && ($evaluation->model_version !== $this->identity($application, $digest)
                || $evaluation->formula_version !== config('ai.matching.formula_version')
                || $evaluation->resume_snapshot_hash !== $application->resume_snapshot_hash)) {
                $state = 'stale';
            }
            $labels = [
                'not_evaluated' => 'Chưa đánh giá', 'pending' => 'Chờ đánh giá', 'processing' => 'Đang đánh giá',
                'completed' => 'Đã đánh giá', 'failed' => 'Đánh giá thất bại',
                'stale' => 'Kết quả cũ, cần đánh giá lại', 'unavailable' => 'AI tạm thời chưa sẵn sàng',
            ];
            $results[$application->id] = [
                'state' => $state, 'label' => $labels[$state],
                'result' => $state === 'completed' ? $evaluation : null,
                'message' => $state === 'failed' && $evaluation->error_code === 'historical_job_analysis_missing'
                    ? 'Phiên bản tin lúc ứng tuyển chưa có phân tích AI hợp lệ. Cần hoàn tất phân tích phiên bản đó trước.'
                    : null,
            ];
        }

        return $results;
    }

    /** Mở đúng snapshot private, kiểm tra namespace, kích thước và hash trước khi dùng bất kỳ phân tích nào. */
    private function snapshotText(Application $application): string
    {
        $path = $application->resume_snapshot_path;
        if (! preg_match('#^application-snapshots/'.$application->candidate_id.'/[a-f0-9-]+\.pdf$#i', $path)
            || ! Storage::disk('local')->exists($path)
            || Storage::disk('local')->size($path) !== $application->resume_snapshot_size) {
            throw new RuntimeException('invalid_application_snapshot');
        }

        return $this->resumeAnalysis->extractPrivatePdf($path, $application->resume_snapshot_hash);
    }

    public function process(int $id, string $token, int $actorId): string
    {
        $evaluation = DB::transaction(function () use ($id, $token) {
            $evaluation = ApplicationAiEvaluation::lockForUpdate()->find($id);
            if (! $evaluation || $evaluation->processing_token !== $token
                || in_array($evaluation->evaluation_status->value, ['completed', 'failed'])) {
                return null;
            }
            if ($evaluation->lease_expires_at?->isFuture()) {
                throw new RuntimeException('evaluation_busy');
            }
            $evaluation->forceFill([
                'evaluation_status' => 'processing', 'attempt_count' => $evaluation->attempt_count + 1,
                'started_at' => now(), 'lease_expires_at' => now()->addSeconds(320), 'next_retry_at' => null,
            ])->save();

            return $evaluation;
        });
        if (! $evaluation) {
            return 'obsolete';
        }
        try {
            $actor = User::findOrFail($actorId);
            $application = Application::findOrFail($evaluation->application_id);
            Gate::forUser($actor)->authorize('viewNotes', $application);
            Gate::forUser($actor)->authorize('viewSnapshot', $application);
            $digest = $this->digest();
            if ($this->identity($application, $digest) !== $evaluation->model_version
                || $application->job_version_id !== $evaluation->job_version_id
                || $application->job_id !== $evaluation->job_id
                || $application->resume_snapshot_hash !== $evaluation->resume_snapshot_hash) {
                throw new RuntimeException('stale_inputs');
            }
            $version = $application->jobVersionQuery()->with('skillRequirements')->firstOrFail();
            if ($version->ai_analysis_status !== 'completed'
                || ! $this->matching->hasAnalysis($version->ai_analysis_result, array_keys(AIOllamaService::JOB_SECTIONS))) {
                throw new RuntimeException('historical_job_analysis_missing');
            }
            $text = $this->snapshotText($application);
            $revision = $application->resumeRevisionQuery()->first();
            $analysis = null;
            if ($revision && $revision->file_hash === $application->resume_snapshot_hash
                && $revision->parsing_status->value === 'completed'
                && $this->matching->hasAnalysis($revision->analysis_result, array_keys(AIOllamaService::SECTIONS))) {
                // Tái kiểm tra bằng chứng với chính snapshot, không tin JSON chỉ vì trạng thái completed.
                try {
                    $analysis = $this->ollama->validateResult($revision->analysis_result, $text);
                } catch (RuntimeException) {
                    $analysis = null;
                }
            }
            // Thiếu phân tích tương ứng: xử lý snapshot riêng trong bộ nhớ, không ghi đè revision hoặc file lịch sử.
            $analysis ??= $this->ollama->analyze($text);
            $cvSkills = JobCatalog::skillsInVerifiedClaims($analysis['technical_skills']);
            $required = $version->skillRequirements->where('is_required', true)->pluck('skill_id')->all();
            $scores = $this->matching->matchSkills($cvSkills->pluck('id')->all(), $required);
            if ($scores['missing_data']) {
                throw new RuntimeException('invalid_skill_catalog');
            }
            $private = [$application->candidate->name, $application->candidate->email, $application->job->company->name];
            $texts = $this->matching->embeddingTexts(
                $analysis, $version->ai_analysis_result,
                $cvSkills->pluck('id')->all(), $version->skillRequirements->pluck('skill_id')->all(), $private
            );
            $semantic = $this->semantic->compare($texts['resume'], $texts['job'], [
                'application', $application->id, $evaluation->model_version, $application->resume_snapshot_hash,
            ]);
            $score = $this->matching->totalScore($semantic['semantic_score'], $scores['skill_score']);
            $fresh = $application->fresh();
            Gate::forUser($actor->fresh())->authorize('viewNotes', $fresh);
            if ($this->identity($fresh, $this->digest()) !== $evaluation->model_version
                || $semantic['model_digest'] !== $digest) {
                throw new RuntimeException('stale_inputs');
            }
            $this->snapshotText($fresh);
            $explanation = implode("\n", $scores['reasons'])."\n60% ngữ nghĩa + 40% kỹ năng; không phải xác suất trúng tuyển."
                ."\nCV snapshot revision ".$application->resume_revision.'; JobVersion '.$version->id
                .'; '.$semantic['model_version'];
            $saved = ApplicationAiEvaluation::whereKey($id)->where('processing_token', $token)->where('evaluation_status', 'processing')
                ->update([
                    'evaluation_status' => 'completed', 'score' => $score, 'semantic_score' => $semantic['semantic_score'],
                    'skill_score' => $scores['skill_score'],
                    'matched_skills' => json_encode($scores['matched_skills'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'missing_skills' => json_encode($scores['missing_skills'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'explanation' => $explanation, 'evaluated_at' => now(), 'lease_expires_at' => null,
                    'next_retry_at' => null, 'error_code' => null,
                ]);

            return $saved === 1 ? 'completed' : 'obsolete';
        } catch (Throwable $exception) {
            $permanent = ['stale_inputs', 'invalid_application_snapshot', 'invalid_private_pdf', 'pdf_hash_mismatch',
                'no_extractable_text', 'historical_job_analysis_missing', 'invalid_skill_catalog',
                'embedding_input_missing', 'embedding_input_too_long', 'embedding_invalid_vectors',
                'embedding_zero_vector', 'embedding_dimension_mismatch', 'invalid_local_endpoint'];
            $terminal = in_array($exception->getMessage(), $permanent, true)
                || $exception instanceof AuthorizationException
                || $evaluation->attempt_count >= 3;
            $known = array_merge($permanent, ['embedding_disabled', 'embedding_request_failed',
                'embedding_model_changed', 'embedding_model_unavailable', 'ollama_response_failed',
                'ollama_output_truncated', 'invalid_ai_output', 'unsupported_ai_claim']);
            $code = in_array($exception->getMessage(), $known, true) ? $exception->getMessage() : 'evaluation_unavailable';
            if ($exception instanceof AuthorizationException) {
                $code = 'access_denied';
            } elseif ($exception instanceof \JsonException) {
                $code = 'invalid_ai_json';
            } elseif ($exception instanceof QueryException) {
                $code = 'evaluation_database_error';
            }
            ApplicationAiEvaluation::whereKey($id)->where('processing_token', $token)->where('evaluation_status', 'processing')
                ->update([
                    'evaluation_status' => $terminal ? 'failed' : 'pending', 'error_code' => $code,
                    'lease_expires_at' => null, 'next_retry_at' => $terminal ? null : now()->addSeconds(30),
                ]);
            if ($terminal) {
                return 'failed';
            }
            throw new RuntimeException('Application AI evaluation temporarily unavailable.');
        }
    }

    public function queueFailed(int $id, string $token): void
    {
        ApplicationAiEvaluation::whereKey($id)->where('processing_token', $token)
            ->whereIn('evaluation_status', ['pending', 'processing'])->update([
                'evaluation_status' => 'failed', 'error_code' => 'queue_failed',
                'lease_expires_at' => null, 'next_retry_at' => null,
            ]);
    }
}
