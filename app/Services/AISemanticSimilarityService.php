<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AISemanticSimilarityService
{
    public function __construct(private AIOllamaService $ollama) {}

    /** Chỉ lấy các mục nghề nghiệp đã kiểm chứng; bỏ học vấn, liên hệ, tuổi, giới tính và địa chỉ. */
    public function prepareText(array $analysis, string $kind, array $skills, array $privateNames = []): string
    {
        $sections = $kind === 'resume'
            ? ['technical_skills', 'professional_roles', 'work_experience', 'projects']
            : ['required_skills', 'preferred_skills', 'professional_roles', 'responsibilities'];
        $parts = $skills;
        foreach ($sections as $section) {
            foreach ($analysis[$section] ?? [] as $claim) {
                $text = trim($claim['text'] ?? '');
                if ($text === '' || trim($claim['evidence'] ?? '') === '') {
                    continue;
                }
                // Bỏ cả claim có dấu hiệu dữ liệu cá nhân, không dùng đặc điểm nhạy cảm để chấm điểm.
                $sensitive = '/(?:@|https?:\/\/|\+?\d[\d ().-]{7,}\d|ngày sinh|năm sinh|giới tính'
                    .'|quốc tịch|tôn giáo|dân tộc|hôn nhân|khuyết tật|sức khỏe|địa chỉ|quê quán'
                    .'|\btuổi\b|gender|nationality|religion|marital|disability|date of birth|\bborn\b'
                    .'|\bmale\b|\bfemale\b|ethnicity|pregnan|mang thai|sexual orientation)/iu';
                if (preg_match($sensitive, $text)) {
                    continue;
                }
                foreach ($privateNames as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $text = preg_replace('/'.preg_quote($name, '/').'/iu', '', $text);
                    }
                }
                $parts[] = $text;
            }
        }
        $parts = array_values(array_unique(array_filter(array_map(
            fn ($part) => trim(preg_replace('/\s+/u', ' ', strip_tags($part))),
            $parts
        ))));

        return mb_substr(implode("\n", $parts), 0, $this->maxCharacters());
    }

    /** Cache chỉ chứa điểm và metadata, không lưu văn bản CV hoặc response thô. */
    public function compare(string $resumeText, string $jobText, array $identity): array
    {
        if (! config('ai.embedding.enabled')) {
            throw new RuntimeException('embedding_disabled');
        }
        if (trim($resumeText) === '' || trim($jobText) === '') {
            throw new RuntimeException('embedding_input_missing');
        }
        $digest = $this->modelDigest();
        $preprocessing = config('ai.embedding.preprocessing_version');
        $resumeChunks = $this->chunks($resumeText);
        $jobChunks = $this->chunks($jobText);
        $key = 'ai-semantic:'.hash('sha256', json_encode([
            $identity, $digest, config('ai.embedding'), $resumeChunks, $jobChunks,
        ], JSON_THROW_ON_ERROR));
        $cache = Cache::store(config('ai.embedding.cache_store'));
        // Khóa dài hơn timeout HTTP; request thứ hai không chạy lại cùng cặp embedding.
        return $cache->lock($key.':lock', 120)->block(2, function () use (
            $cache, $key, $digest, $preprocessing, $resumeChunks, $jobChunks
        ) {
            $cached = $cache->get($key);
            if (is_array($cached)) {
                $cached['cache_hit'] = true;

                return $cached;
            }
            $chunks = array_merge($resumeChunks, $jobChunks);
            $response = $this->request()->post($this->ollama->endpoint().'/api/embed', [
                'model' => config('ai.embedding.model'), 'input' => $chunks,
                'truncate' => false, 'keep_alive' => '30s',
                // EmbeddingGemma cần batch chứa đủ token của đoạn, không dùng batch nhỏ của generation.
                'options' => ['num_ctx' => 2048, 'num_batch' => 2048],
            ]);
            if ($response->status() === 413) {
                throw new RuntimeException('embedding_input_too_long');
            }
            if (! $response->successful() || $response->json('model') !== config('ai.embedding.model')) {
                throw new RuntimeException('embedding_request_failed');
            }
            $vectors = $response->json('embeddings');
            if (! is_array($vectors) || ! array_is_list($vectors) || count($vectors) !== count($chunks)) {
                throw new RuntimeException('embedding_invalid_vectors');
            }
            $vectors = array_map(fn ($vector) => $this->normalizeVector($vector), $vectors);
            $dimension = count($vectors[0]);
            foreach ($vectors as $vector) {
                if (count($vector) !== $dimension) {
                    throw new RuntimeException('embedding_dimension_mismatch');
                }
            }
            // Không cache nếu tag model bị đổi trong lúc Ollama đang tính vector.
            if ($this->modelDigest() !== $digest) {
                throw new RuntimeException('embedding_model_changed');
            }
            $resumeVector = $this->meanVector(array_slice($vectors, 0, count($resumeChunks)), $resumeChunks);
            $jobVector = $this->meanVector(array_slice($vectors, count($resumeChunks)), $jobChunks);
            $cosine = $this->cosine($resumeVector, $jobVector);
            $result = [
                'semantic_score' => 100 * max(0, min(1, $cosine)),
                'cosine' => $cosine, 'model_digest' => $digest,
                'model_version' => config('ai.embedding.model').'@'.$digest.'/'.$preprocessing,
                'preprocessing_version' => $preprocessing, 'dimensions' => $dimension,
                'chunks' => count($chunks), 'cache_hit' => false,
            ];
            $cache->put($key, $result, max(1, (int) config('ai.embedding.cache_seconds')));

            return $result;
        });
    }

    /** Đọc digest thực mỗi lần dùng cache để tag mới không nhận vector của model cũ. */
    public function modelDigest(): string
    {
        $response = $this->request()->timeout(5)->get($this->ollama->endpoint().'/api/tags');
        if (! $response->successful()) {
            throw new RuntimeException('embedding_model_unavailable');
        }
        foreach ($response->json('models', []) as $model) {
            if (($model['name'] ?? '') === config('ai.embedding.model')
                && preg_match('/^[a-f0-9]{64}$/', $model['digest'] ?? '')) {
                return $model['digest'];
            }
        }

        throw new RuntimeException('embedding_model_unavailable');
    }

    /** Chuẩn hóa L2 trước tích vô hướng; chia theo trị lớn nhất để tránh tràn số. */
    public function normalizeVector(mixed $vector): array
    {
        if (! is_array($vector) || ! array_is_list($vector) || count($vector) < 2 || count($vector) > 4096) {
            throw new RuntimeException('embedding_invalid_vectors');
        }
        $scale = 0.0;
        foreach ($vector as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new RuntimeException('embedding_invalid_vectors');
            }
            $scale = max($scale, abs($value));
        }
        if ($scale == 0) {
            throw new RuntimeException('embedding_zero_vector');
        }
        $scaled = array_map(fn ($value) => $value / $scale, $vector);
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $scaled)));

        return array_map(fn ($value) => $value / $norm, $scaled);
    }

    public function cosine(array $first, array $second): float
    {
        $first = $this->normalizeVector($first);
        $second = $this->normalizeVector($second);
        if (count($first) !== count($second)) {
            throw new RuntimeException('embedding_dimension_mismatch');
        }
        $dot = 0.0;
        foreach ($first as $index => $value) {
            $dot += $value * $second[$index];
        }

        return max(-1, min(1, $dot));
    }

    /** Cùng giới hạn/cách chia cho CV và Job; không để Ollama âm thầm cắt theo token. */
    private function chunks(string $text): array
    {
        $text = mb_substr(trim($text), 0, $this->maxCharacters());

        return mb_str_split($text, max(100, min(1000, (int) config('ai.embedding.chunk_characters'))));
    }

    private function maxCharacters(): int
    {
        return max(100, min(6000, (int) config('ai.embedding.max_characters')));
    }

    /** Trung bình vector các đoạn theo số ký tự, rồi chuẩn hóa lại trước cosine. */
    private function meanVector(array $vectors, array $chunks): array
    {
        $mean = array_fill(0, count($vectors[0]), 0.0);
        foreach ($vectors as $index => $vector) {
            foreach ($vector as $dimension => $value) {
                $mean[$dimension] += $value * mb_strlen($chunks[$index]);
            }
        }

        return $this->normalizeVector($mean);
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::connectTimeout(3)->timeout(max(5, min(90, (int) config('ai.embedding.timeout'))))
            ->withOptions(['allow_redirects' => false, 'proxy' => '']);
    }
}
