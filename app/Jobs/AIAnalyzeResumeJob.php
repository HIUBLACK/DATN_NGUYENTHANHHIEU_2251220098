<?php

namespace App\Jobs;

use App\Services\AIResumeAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AIAnalyzeResumeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public int $revisionId, public string $token)
    {
        // Queue AI riêng để upload không chờ model và không ảnh hưởng luồng gửi email.
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
    public function handle(AIResumeAnalysisService $analysis): void
    {
        // Khóa dùng chung giữa các worker, hạn chế nhiều tác vụ cùng chiếm bộ nhớ GPU.
        $lock = Cache::store('database')->lock('ollama-cv-single-worker', 300);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $outcome = $analysis->process($this->revisionId, $this->token);
            if ($outcome === 'failed') {
                // Không để queue báo thành công khi revision hiện hành đã thất bại.
                $this->fail(new \RuntimeException('CV analysis failed; inspect revision error_code.'));
            } elseif ($outcome === 'busy') {
                $this->release(30);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Chuyển trạng thái thất bại mà không thay đổi PDF hoặc hồ sơ ứng tuyển.
     */
    public function failed(?Throwable $exception): void
    {
        app(AIResumeAnalysisService::class)->queueFailed($this->revisionId, $this->token);
    }
}
