<?php

namespace App\Jobs;

use App\Services\AIRecommendationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AIRecommendJobsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 240;
    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public string $token, public array $targets, public int $index)
    {
        $this->onConnection('ai')->onQueue('ai')->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 90];
    }

    /** Dùng cùng khóa với Qwen để không tranh GPU; một tác vụ chỉ xử lý một cặp CV–Job. */
    public function handle(AIRecommendationService $service): void
    {
        $lock = Cache::store('database')->lock('ollama-cv-single-worker', 300);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $outcome = $service->process($this->runId, $this->token, $this->targets, $this->index);
            if ($outcome === 'failed') {
                $this->fail(new \RuntimeException('Recommendation data is not usable.'));
            }
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'recommendation_busy') {
                throw $exception;
            }
            $this->release(30);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AIRecommendationService::class)->failRun($this->runId, $this->token);
    }
}
