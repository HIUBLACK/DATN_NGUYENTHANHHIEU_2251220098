<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$semantic = app(App\Services\AISemanticSimilarityService::class);
$matching = app(App\Services\AIMatchingService::class);
$cv = [
    'professional_roles' => [['text' => 'Lập trình viên Backend', 'evidence' => 'Lập trình viên Backend']],
    'work_experience' => [[
        'text' => 'Phát triển API bằng PHP và Laravel, thiết kế cơ sở dữ liệu MySQL và tối ưu truy vấn.',
        'evidence' => 'Phát triển API bằng PHP và Laravel, thiết kế cơ sở dữ liệu MySQL và tối ưu truy vấn.',
    ]],
    'projects' => [[
        'text' => 'Xây dựng hệ thống quản lý tuyển dụng, phân quyền người dùng và viết kiểm thử API.',
        'evidence' => 'Xây dựng hệ thống quản lý tuyển dụng, phân quyền người dùng và viết kiểm thử API.',
    ]],
];
$job = [
    'professional_roles' => [['text' => 'Lập trình viên Backend PHP', 'evidence' => 'Lập trình viên Backend PHP']],
    'responsibilities' => [[
        'text' => 'Xây dựng và bảo trì REST API với Laravel, thiết kế MySQL, triển khai ứng dụng bằng Docker.',
        'evidence' => 'Xây dựng và bảo trì REST API với Laravel, thiết kế MySQL, triển khai ứng dụng bằng Docker.',
    ]],
];
$cvText = $semantic->prepareText($cv, 'resume', ['PHP', 'Laravel', 'MySQL']);
$jobText = $semantic->prepareText($job, 'job', ['PHP', 'Laravel', 'MySQL', 'Docker']);
$identity = ['synthetic-vietnamese-probe', 'resume-revision-1', 'job-version-1', bin2hex(random_bytes(8))];
$start = microtime(true);
$result = $semantic->compare($cvText, $jobText, $identity);
$firstSeconds = microtime(true) - $start;
$start = microtime(true);
$cached = $semantic->compare($cvText, $jobText, $identity);
$cacheSeconds = microtime(true) - $start;
$skills = App\Support\JobCatalog::all('skills');
$cvIds = $skills->whereIn('name', ['PHP', 'Laravel', 'MySQL'])->pluck('id')->all();
$jobIds = $skills->whereIn('name', ['PHP', 'Laravel', 'MySQL', 'Docker'])->pluck('id')->all();
$skillResult = $matching->matchSkills($cvIds, $jobIds);
$loaded = Illuminate\Support\Facades\Http::timeout(5)->get('http://127.0.0.1:11434/api/ps')->json('models', []);
echo json_encode([
    'source' => 'Synthetic Vietnamese career facts, no real CV or database writes',
    'embedding' => $result,
    'skill_score' => $skillResult['skill_score'],
    'score' => $matching->totalScore($result['semantic_score'], $skillResult['skill_score']),
    'first_seconds' => round($firstSeconds, 3),
    'cached_seconds' => round($cacheSeconds, 3),
    'second_cache_hit' => $cached['cache_hit'],
    'php_peak_bytes' => memory_get_peak_usage(true),
    'loaded_models' => array_map(fn ($model) => array_intersect_key($model, array_flip([
        'name', 'size', 'size_vram', 'context_length',
    ])), $loaded),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
