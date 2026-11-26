<?php

namespace App\Jobs;

use App\Services\AIApplicationEvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class AIEvaluateApplicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public int $evaluationId, public string $token, public int $actorId)
    {
        $this->onConnection('ai')->onQueue('ai')->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 90];
    }

    /** Dùng chung khóa GPU với CV, Job và Recommendation; không chạy model đồng thời. */
    public function handle(AIApplicationEvaluationService $service): void
    {
        $lock = Cache::store('database')->lock('ollama-cv-single-worker', 350);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $outcome = $service->process($this->evaluationId, $this->token, $this->actorId);
            if ($outcome === 'failed') {
                $this->fail(new RuntimeException('Application AI evaluation failed.'));
            }
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'evaluation_busy') {
                throw $exception;
            }
            $this->release(30);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AIApplicationEvaluationService::class)->queueFailed($this->evaluationId, $this->token);
    }
}
