<?php

return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'model' => env('OLLAMA_MODEL', 'qwen3:1.7b'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    'max_characters' => (int) env('OLLAMA_MAX_CHARACTERS', 10000),
    'context' => (int) env('OLLAMA_CONTEXT', 8192),
    'output_tokens' => (int) env('OLLAMA_OUTPUT_TOKENS', 2500),
];
