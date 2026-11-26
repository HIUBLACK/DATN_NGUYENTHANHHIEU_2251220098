<?php

namespace App\Jobs;

use App\Models\JobVersion;
use App\Services\AIJobAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIAnalyzeJobJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public int $versionId, public string $token)
    {
        // Queue AI riêng để duyệt tin không chờ model và không ảnh hưởng luồng gửi email.
        $this->onConnection('ai')->onQueue('ai')->afterCommit();
    }

    /**
     * Giãn thời gian thử lại để tránh gọi liên tục khi Ollama chưa sẵn sàng.
     */
    public function backoff(): array
    {
        return [30, 90];
    }

    /**
     * Xử lý tuần tự bằng khóa dùng chung, luôn nhả khóa sau khi hoàn tất.
     */
    public function handle(AIJobAnalysisService $analysis): void
    {
        $this->trace('started');
        // Khóa dùng chung giữa các worker, hạn chế nhiều tác vụ cùng chiếm bộ nhớ GPU.
        $lock = Cache::store('database')->lock('ollama-cv-single-worker', 300);
        if (! $lock->get()) {
            $this->trace('lock_busy');
            $this->release(30);

            return;
        }
        try {
            $outcome = $analysis->process($this->versionId, $this->token);
            $this->trace($outcome);
            if ($outcome === 'busy') {
                $this->release(30);
            }
        } catch (\RuntimeException $exception) {
            $this->trace('exception');
            if ($exception->getMessage() !== 'Job AI analysis is terminal.') {
                throw $exception;
            }
            // Lỗi vĩnh viễn/hết lượt thử phải FAIL rõ ràng, không nhận lại rồi DONE giả.
            $this->fail($exception);
        } finally {
            $lock->release();
        }
    }

    /**
     * Chuyển trạng thái thất bại mà không thay đổi nội dung hoặc trạng thái tin tuyển dụng.
     */
    public function failed(?Throwable $exception): void
    {
        app(AIJobAnalysisService::class)->queueFailed($this->versionId, $this->token);
        $this->trace('terminal_failed');
    }

    /** Đối chiếu worker với database, chỉ ghi token băm và metadata không chứa nội dung tin. */
    private function trace(string $outcome): void
    {
        $version = JobVersion::find($this->versionId);
        Log::info('Job AI queue trace.', [
            'queue_job_id' => $this->job?->getJobId(),
            'queue_uuid' => $this->job?->uuid(),
            'queue_attempts' => $this->job?->attempts(),
            'job_version_id' => $this->versionId,
            'token_hash' => substr(hash('sha256', $this->token), 0, 12),
            'token_matches' => $version?->ai_processing_token === $this->token,
            'attempt_count' => $version?->ai_attempt_count,
            'status' => $version?->ai_analysis_status,
            'error_code' => $version?->ai_error_code,
            'has_result' => $version?->ai_analysis_result !== null,
            'outcome' => $outcome,
            'database' => DB::connection()->getDatabaseName(),
        ]);
    }
}
