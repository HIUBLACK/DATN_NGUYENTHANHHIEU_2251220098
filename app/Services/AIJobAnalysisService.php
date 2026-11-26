<?php

namespace App\Services;

use App\Enums\JobReviewStatus;
use App\Jobs\AIAnalyzeJobJob;
use App\Models\JobVersion;
use App\Support\JobCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AIJobAnalysisService
{
    public function __construct(private AIOllamaService $ollama) {}

    /** Chỉ chuẩn bị phân tích sau commit; mọi lỗi AI không làm đảo kết quả duyệt. */
    public function afterApproval(int $versionId): void
    {
        DB::afterCommit(function () use ($versionId) {
            try {
                $this->enqueue($versionId);
            } catch (Throwable) {
                Log::warning('Job AI enqueue failed.', ['job_version_id' => $versionId]);
            }
        });
    }

    /** Khóa phiên bản để yêu cầu lặp không tạo thêm job; failed được thử lại với token mới. */
    public function enqueue(int $versionId): void
    {
        DB::transaction(function () use ($versionId) {
            $version = JobVersion::lockForUpdate()->findOrFail($versionId);
            if ($version->review_status !== JobReviewStatus::Approved
                || ! in_array($version->ai_analysis_status, [null, 'failed'], true)) {
                return;
            }
            $token = (string) Str::uuid();
            $this->write($versionId, [
                'ai_analysis_status' => 'pending',
                'ai_processing_token' => $token,
                'ai_attempt_count' => 0,
                'ai_error_code' => null,
                'ai_started_at' => null,
                'ai_lease_expires_at' => null,
                'ai_next_retry_at' => null,
            ]);
            DB::afterCommit(function () use ($versionId, $token) {
                try {
                    AIAnalyzeJobJob::dispatch($versionId, $token);
                } catch (Throwable) {
                    $this->queueFailed($versionId, $token);
                }
            });
        });
    }

    /** Nhận quyền xử lý trong transaction ngắn; gọi Ollama ngoài khóa database. */
    public function process(int $versionId, string $token): string
    {
        $version = DB::transaction(function () use ($versionId, $token) {
            $version = JobVersion::lockForUpdate()->find($versionId);
            if ($version && $version->ai_processing_token === $token && $version->ai_analysis_status === 'failed') {
                throw new RuntimeException('Job AI analysis is terminal.');
            }
            if (! $version) {
                return 'missing';
            }
            if ($version->ai_processing_token !== $token) {
                return 'stale_token';
            }
            if ($version->ai_analysis_status === 'completed') {
                return 'already_completed';
            }
            if (! $this->eligible($version, $token)) {
                return 'ineligible';
            }
            if ($version->ai_attempt_count >= 3) {
                throw new RuntimeException('Job AI analysis is terminal.');
            }
            if ($version->ai_analysis_status === 'processing' && $version->ai_lease_expires_at?->isFuture()) {
                return 'busy';
            }
            $this->write($versionId, [
                'ai_analysis_status' => 'processing',
                'ai_attempt_count' => $version->ai_attempt_count + 1,
                'ai_started_at' => now(),
                'ai_lease_expires_at' => now()->addSeconds(300),
                'ai_next_retry_at' => null,
            ]);

            return $version;
        });
        if (is_string($version)) {
            return $version;
        }

        $stage = 'source';
        try {
            $structured = $this->structuredRequirements($version);
            $hasSupplement = $this->hasSupplement($version);
            if (! $hasSupplement) {
                // Chỉ bỏ qua model khi không có văn bản bổ sung và danh mục gốc đầy đủ.
                if (! $structured['job_position_id'] || ! $structured['job_specialization_id']
                    || ! $structured['job_level_id'] || ! $structured['position']) {
                    throw new RuntimeException('invalid_structured_requirements');
                }
                $result = ['schema_version' => 1, 'rejected_claim_count' => 0]
                    + array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []);
                $result['extraction_outcome'] = 'structured_only_no_supplement';
            } else {
                $stage = 'ollama';
                $result = $this->ollama->analyzeJob($this->sourceText($version, $structured));
                $claimCount = 0;
                foreach (array_keys(AIOllamaService::JOB_SECTIONS) as $section) {
                    $claimCount += count($result[$section]);
                }
                if ($claimCount === 0) {
                    throw new RuntimeException('empty_job_extraction');
                }
                $result['extraction_outcome'] = 'verified_claims';
            }
            $stage = 'storage';
            $result = $this->normalizeSkills($result, $structured);
            $result['structured_requirements'] = $structured;
            $saved = DB::transaction(function () use ($versionId, $token, $result) {
                $current = JobVersion::lockForUpdate()->findOrFail($versionId);
                if (! $this->eligible($current, $token) || $current->ai_analysis_status !== 'processing') {
                    return false;
                }
                // Chỉ ghi metadata của versionId này; không đổi pointer hoặc updated_at nghiệp vụ.
                $this->write($versionId, [
                    'ai_analysis_result' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'ai_analysis_status' => 'completed',
                    'ai_analyzed_at' => now(),
                    'ai_model_version' => mb_substr(config('ai.model').'/job-schema-1', 0, 100),
                    'ai_error_code' => null,
                    'ai_lease_expires_at' => null,
                    'ai_next_retry_at' => null,
                ]);

                return true;
            });

            return $saved ? 'completed' : 'superseded';
        } catch (Throwable $exception) {
            $code = $this->errorCode($exception, $stage);
            // Chỉ log metadata, không ghi exception gốc có thể chứa prompt/SQL/response.
            Log::warning('Job AI attempt failed.', [
                'job_version_id' => $versionId, 'stage' => $this->errorStage($code, $stage), 'error_code' => $code,
                'token_hash' => substr(hash('sha256', $token), 0, 12),
                'database' => DB::connection()->getDatabaseName(),
            ]);
            $this->recordFailure($versionId, $token, in_array($code, [
                'invalid_local_endpoint', 'invalid_structured_requirements', 'invalid_source', 'database_constraint_failed',
            ], true), $code);
            if (JobVersion::whereKey($versionId)->where('ai_processing_token', $token)
                ->where('ai_analysis_status', 'failed')->exists()) {
                throw new RuntimeException('Job AI analysis is terminal.');
            }
            // Không đưa response/model exception vào log vì có thể chứa nội dung tin.
            throw new RuntimeException('Job AI analysis failed; retry is limited.');
        }
    }

    /** Chỉ nhận các giá trị rỗng/không có rõ ràng; không suy đoán nội dung ít chữ là vô nghĩa. */
    private function hasSupplement(JobVersion $version): bool
    {
        foreach (['requirements', 'description', 'benefits', 'experience_summary',
            'experience_min', 'experience_max'] as $field) {
            $value = mb_strtolower(trim($this->ollama->normalize((string) $version->$field), " .\t\n\r"));
            if (! in_array($value, ['', 'không có', 'không có thông tin', 'none', 'n/a', 'not specified'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Tách lỗi JSON và bằng chứng khỏi lỗi kết nối để thống kê đúng giai đoạn. */
    private function errorStage(string $code, string $stage): string
    {
        return match ($code) {
            'invalid_ai_json', 'invalid_ai_output', 'ollama_output_truncated' => 'json',
            'unsupported_ai_claim', 'empty_job_extraction' => 'evidence',
            default => $stage,
        };
    }

    /** Giữ nguyên dữ liệu danh mục thật, không để AI sửa yêu cầu Recruiter đã nhập. */
    private function structuredRequirements(JobVersion $version): array
    {
        $version->load(['skillRequirements']);

        return [
            'job_version_id' => $version->id,
            'job_position_id' => $version->job_position_id,
            'position' => $version->positionCatalog?->name ?? $version->position,
            'job_specialization_id' => $version->job_specialization_id,
            'specialization' => $version->specialization?->name,
            'job_level_id' => $version->job_level_id,
            'level' => $version->level?->name,
            'province_code' => $version->province_code,
            'location' => $version->location,
            'work_mode' => $version->work_mode,
            'employment_type' => $version->employment_type,
            'skills' => $version->skills->map(fn ($skill) => [
                'skill_id' => $skill->id,
                'name' => $skill->name,
                'is_required' => (bool) $skill->pivot->is_required,
            ])->all(),
        ];
    }

    /** Đưa dữ liệu có cấu trúc lên trước; không bổ sung kinh nghiệm nếu tin không có. */
    private function sourceText(JobVersion $version, array $structured): string
    {
        $parts = [json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
        foreach (['title', 'position', 'requirements', 'description', 'experience_summary',
            'experience_min', 'experience_max', 'benefits'] as $field) {
            if ($version->$field !== null && $version->$field !== '') {
                $parts[] = $field.': '.$version->$field;
            }
        }

        return implode("\n", $parts);
    }

    /** Chỉ đối chiếu tên kỹ năng hiện có; yêu cầu có cấu trúc luôn được ưu tiên. */
    private function normalizeSkills(array $result, array $structured): array
    {
        $names = collect(array_merge($result['required_skills'], $result['preferred_skills']))
            ->map(fn ($item) => mb_strtolower(trim($item['text'])))->unique()->all();
        $catalog = JobCatalog::matchingSkills($names)->keyBy(fn ($skill) => mb_strtolower(trim($skill->name)));
        $declared = collect($structured['skills'])->keyBy('skill_id');
        $normalized = ['required_skills' => [], 'preferred_skills' => []];
        foreach (array_keys($normalized) as $section) {
            foreach ($result[$section] as $item) {
                $skill = $catalog->get(mb_strtolower(trim($item['text'])));
                $item['skill_id'] = $skill?->id;
                $target = $section;
                if ($skill && $declared->has($skill->id)) {
                    $target = $declared[$skill->id]['is_required'] ? 'required_skills' : 'preferred_skills';
                }
                $key = $skill ? 'id:'.$skill->id : 'text:'.mb_strtolower($item['text']);
                $normalized[$target][$key] = $item;
            }
        }
        foreach ($normalized as $section => $items) {
            $result[$section] = array_values($items);
        }

        return $result;
    }

    /** Chỉ token hiện hành được ghi; V1 hoàn tất trễ vẫn lưu riêng, không chạm V2. */
    private function eligible(JobVersion $version, string $token): bool
    {
        return $version->review_status === JobReviewStatus::Approved
            && $version->ai_processing_token === $token
            && in_array($version->ai_analysis_status, ['pending', 'processing'], true);
    }

    /** Ghi lỗi cuối cùng khi queue hết số lần thử hoặc không dispatch được. */
    public function queueFailed(int $versionId, string $token): void
    {
        $this->recordFailure($versionId, $token, true);
    }

    /** Giữ mã lỗi cụ thể qua callback queue, không biến mọi lỗi thành queue_failed. */
    private function recordFailure(int $versionId, string $token, bool $terminal, ?string $code = null): void
    {
        DB::transaction(function () use ($versionId, $token, $terminal, $code) {
            $version = JobVersion::lockForUpdate()->find($versionId);
            if (! $version || ! $this->eligible($version, $token)) {
                return;
            }
            $failed = $terminal || $version->ai_attempt_count >= 3;
            $delay = $version->ai_attempt_count === 1 ? 30 : 90;
            $this->write($versionId, [
                'ai_analysis_status' => $failed ? 'failed' : 'pending',
                'ai_error_code' => $code ?? $version->ai_error_code ?? 'queue_failed',
                'ai_lease_expires_at' => null,
                'ai_next_retry_at' => $failed ? null : now()->addSeconds($delay),
            ]);
        });
    }

    /** Phân biệt lỗi model, kết nối và lưu dữ liệu bằng mã an toàn. */
    private function errorCode(Throwable $exception, string $stage): string
    {
        // Sai cấu trúc/ràng buộc dữ liệu không tự hết khi retry; deadlock/kết nối vẫn có thể thử lại.
        if ($exception instanceof QueryException
            && in_array(substr((string) ($exception->errorInfo[0] ?? ''), 0, 2), ['23', '42'], true)) {
            return 'database_constraint_failed';
        }
        if ($exception instanceof ModelNotFoundException) {
            return 'invalid_source';
        }
        if (in_array($exception->getMessage(), [
            'unsupported_ai_claim', 'invalid_ai_output', 'ollama_response_failed', 'invalid_local_endpoint',
            'invalid_structured_requirements', 'empty_job_extraction', 'ollama_output_truncated',
        ], true)) {
            return $exception->getMessage();
        }
        if ($exception instanceof \JsonException) {
            return 'invalid_ai_json';
        }
        if ($exception instanceof ConnectionException) {
            return 'ollama_connection_failed';
        }

        return $stage === 'storage' ? 'analysis_storage_failed' : 'analysis_failed';
    }

    /** Query Builder không tự sửa updated_at của nội dung tin đã được duyệt. */
    private function write(int $versionId, array $values): void
    {
        DB::table('job_versions')->where('id', $versionId)->update($values);
    }
}
