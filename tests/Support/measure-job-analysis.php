<?php

use App\Services\AIOllamaService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$samples = require __DIR__.'/job-analysis-samples.php';
foreach ($samples as $name => $text) {
    if (isset($argv[1]) && ! in_array($name, explode(',', $argv[1]), true)) {
        continue;
    }
    $start = microtime(true);
    try {
        $result = app(AIOllamaService::class)->analyzeJob($text);
        $count = 0;
        foreach (array_keys(AIOllamaService::JOB_SECTIONS) as $section) {
            $count += count($result[$section]);
        }
        echo json_encode(['sample' => $name, 'valid' => true, 'claims' => $count,
            'rejected' => $result['rejected_claim_count'], 'seconds' => round(microtime(true) - $start, 1)]).PHP_EOL;
    } catch (Throwable $exception) {
        $code = in_array($exception->getMessage(), ['unsupported_ai_claim', 'invalid_ai_output',
            'ollama_response_failed', 'invalid_local_endpoint', 'ollama_output_truncated']) ? $exception->getMessage() : get_class($exception);
        echo json_encode(['sample' => $name, 'valid' => false, 'error' => $code,
            'seconds' => round(microtime(true) - $start, 1)]).PHP_EOL;
    }
}
