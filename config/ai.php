<?php

return [
    'recommendation' => [
        'max_jobs' => 20,
        'page_size' => 10,
        'retry_cooldown' => 60,
    ],
    'embedding' => [
        'enabled' => env('AI_EMBEDDING_ENABLED', true),
        'model' => 'embeddinggemma:300m',
        // Chuẩn hóa thứ tự kỹ năng cho cả Recommendation và Evaluation; cache cũ tự hết hạn.
        'preprocessing_version' => 'career-2',
        'max_characters' => 6000,
        'chunk_characters' => 1000,
        'timeout' => 90,
        'cache_store' => env('AI_EMBEDDING_CACHE_STORE', 'file'),
        'cache_seconds' => 3600,
    ],
    // Công thức đã chốt; thay trọng số cần phê duyệt và phiên bản công thức mới.
    'matching' => [
        'formula_version' => 'v1',
        'semantic_weight' => 0.6,
        'skill_weight' => 0.4,
    ],
    'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'model' => env('OLLAMA_MODEL', 'qwen3:1.7b'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    'max_characters' => (int) env('OLLAMA_MAX_CHARACTERS', 10000),
    'context' => (int) env('OLLAMA_CONTEXT', 8192),
    'output_tokens' => (int) env('OLLAMA_OUTPUT_TOKENS', 2500),
];
