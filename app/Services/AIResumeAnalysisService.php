<?php

namespace App\Services;

use App\Enums\ResumeParsingStatus;
use App\Jobs\AIAnalyzeResumeJob;
use App\Models\Resume;
use App\Models\ResumeRevision;
use App\Models\ResumeSkill;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class AIResumeAnalysisService
{
    public function __construct(private AIOllamaService $ollama) {}

    /**
     * Đưa revision vào queue sau commit, dùng token mới cho mỗi lượt phân tích.
     */
    public function enqueue(ResumeRevision $revision): void
    {
        // Mỗi lượt thử có token mới để callback của lượt cũ không ghi đè kết quả.
        $token = (string) Str::uuid();
        $revision->forceFill([
            'parsing_status' => 'pending',
            'processing_token' => $token,
            'attempt_count' => 0,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'lease_expires_at' => null,
            'next_retry_at' => null,
        ])->save();
        // Worker chỉ được chạy sau khi revision đã lưu chắc chắn vào database.
        DB::afterCommit(function () use ($revision, $token) {
            try {
                AIAnalyzeResumeJob::dispatch($revision->id, $token);
            } catch (Throwable) {
                // CV đã lưu thành công; lỗi queue không được đi vào nhánh xóa file upload.
                try {
                    $this->queueFailed($revision->id, $token);
                } catch (Throwable) {
                    Log::warning('CV analysis enqueue failed.', ['revision_id' => $revision->id]);
                }
            }
        });
    }

    /**
     * Chỉ cho chủ CV thử lại revision hiện tại đã phân tích thất bại.
     */
    public function retry(User $user, string $publicId): void
    {
        // Khóa CV và revision hiện tại để hai yêu cầu thử lại không tạo hai lượt xử lý.
        DB::transaction(function () use ($user, $publicId) {
            $resume = Resume::forCandidate($user->id)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $resume);
            $revision = $resume->currentRevision()->lockForUpdate()->firstOrFail();
            if ($revision->parsing_status !== ResumeParsingStatus::Failed) {
                throw ValidationException::withMessages(['cv' => 'Chỉ phân tích lại CV đã thất bại.']);
            }
            $this->enqueue($revision);
        });
    }

    /**
     * Đọc PDF và phân tích ngoài transaction; kiểm tra lại revision trước khi lưu.
     */
    public function process(int $id, string $token): string
    {
        // Nhận quyền xử lý trong transaction ngắn, không giữ khóa database khi chờ AI.
        $revision = $this->locked($id, function ($resume, $revision) use ($token) {
            if (! $this->eligible($resume, $revision, $token)) {
                return 'obsolete';
            }
            if ($revision->parsing_status === ResumeParsingStatus::Completed) {
                return 'completed';
            }
            if ($revision->parsing_status === ResumeParsingStatus::Failed || $revision->attempt_count >= 3) {
                return 'failed';
            }
            if ($revision->parsing_status === ResumeParsingStatus::Processing && $revision->lease_expires_at?->isFuture()) {
                return 'busy';
            }
            $revision->forceFill([
                'parsing_status' => 'processing',
                'attempt_count' => $revision->attempt_count + 1,
                'started_at' => now(),
                'lease_expires_at' => now()->addSeconds(300),
                'next_retry_at' => null,
            ])->save();

            return $revision;
        });
        if (! $revision instanceof ResumeRevision) {
            $outcome = $revision ?? 'missing';
            Log::info('CV analysis not executed.', ['revision_id' => $id, 'outcome' => $outcome]);

            return $outcome;
        }
        $textPath = 'resume-analysis/'.$revision->resume_id.'/'.$revision->revision.'/'.$token.'.txt';
        $stage = 'pdf';
        try {
            $disk = Storage::disk('local');
            $text = $this->extractPdfText($revision);
            $stage = 'ollama';
            $result = $this->ollama->analyze($text);
            $stage = 'storage';
            // Trong lúc chờ Ollama, Candidate có thể thay hoặc xóa CV nên phải kiểm tra lại.
            $saved = $this->locked($id, function ($resume, $current) use ($token, $result, $textPath, $text, $disk) {
                if (! $this->eligible($resume, $current, $token) || $current->parsing_status !== ResumeParsingStatus::Processing) {
                    return false;
                }
                if (! $disk->put($textPath, $text)) {
                    throw new RuntimeException('private_text_write_failed');
                }
                $current->forceFill([
                    'analysis_result' => $result,
                    'parsing_status' => 'completed',
                    'parsed_at' => now(),
                    'extracted_text_path' => $textPath,
                    'parser_version' => mb_substr('ollama/'.config('ai.model').'/schema-1', 0, 100),
                    'error_code' => null,
                    'error_message' => null,
                    'lease_expires_at' => null,
                    'next_retry_at' => null,
                ])->save();
                // Chỉ liên kết kỹ năng có trong danh mục; không tự tạo kỹ năng từ câu trả lời AI.
                $names = array_map(fn ($item) => mb_strtolower(trim($item['text'])), $result['technical_skills']);
                $current->skills()->delete();
                foreach (Skill::where('is_active', true)->whereIn(DB::raw('LOWER(name)'), $names)->get() as $skill) {
                    $item = new ResumeSkill;
                    $item->forceFill([
                        'resume_id' => $resume->id,
                        'resume_revision' => $current->revision,
                        'skill_id' => $skill->id,
                        'source' => 'model',
                    ])->save();
                }

                return true;
            });
            if (! $saved) {
                $disk->delete($textPath);
            }

            return $saved ? 'completed' : 'obsolete';
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($textPath);
            $code = $this->errorCode($exception, $stage);
            // Chỉ ghi mã lỗi trong danh sách cho phép; không log CV, response hay exception gốc.
            Log::warning('CV analysis attempt failed.', [
                'revision_id' => $id, 'stage' => $stage, 'error_code' => $code,
                'attempt' => $revision->attempt_count,
            ]);
            $terminal = $this->locked($id, function ($resume, $current) use ($token, $code) {
                if ($this->eligible($resume, $current, $token) && $current->parsing_status !== ResumeParsingStatus::Completed) {
                    // PDF sai/hỏng không thể sửa bằng retry; lỗi mạng/model được thử lại hữu hạn.
                    $terminal = in_array($code, [
                        'no_extractable_text', 'invalid_private_pdf', 'pdf_hash_mismatch',
                        'pdf_parse_failed', 'invalid_local_endpoint',
                    ], true) || $current->attempt_count >= 3;
                    $current->forceFill([
                        'parsing_status' => $terminal ? 'failed' : 'pending',
                        'error_code' => $code,
                        'error_message' => 'Không phân tích được CV. Hãy kiểm tra PDF hoặc thử lại sau.',
                        'lease_expires_at' => null,
                        'next_retry_at' => $terminal ? null : now()->addSeconds($current->attempt_count === 1 ? 30 : 90),
                    ])->save();

                    return $terminal;
                }

                return true;
            });
            if ($terminal) {
                return 'failed';
            }
            // Worker nhận exception mới, không chứa dữ liệu nhạy cảm, để thực hiện backoff.
            throw new RuntimeException('CV analysis failed; retry is limited.');
        }
    }

    /** Phân loại lỗi bằng mã an toàn, giữ nguyên JSON/evidence validation. */
    private function errorCode(Throwable $exception, string $stage): string
    {
        $allowed = [
            'no_extractable_text', 'invalid_private_pdf', 'pdf_hash_mismatch',
            'invalid_local_endpoint', 'invalid_ai_output', 'unsupported_ai_claim',
            'ollama_response_failed', 'private_text_write_failed',
        ];
        if (in_array($exception->getMessage(), $allowed, true)) {
            return $exception->getMessage();
        }
        if ($exception instanceof ConnectionException) {
            return 'ollama_connection_failed';
        }
        if ($exception instanceof \JsonException) {
            return 'invalid_ai_json';
        }

        return match ($stage) {
            'pdf' => 'pdf_parse_failed',
            'storage' => 'analysis_storage_failed',
            default => 'analysis_failed',
        };
    }

    /**
     * Đọc đúng PDF riêng tư theo hash, chỉ lấy văn bản trong giới hạn; không OCR.
     */
    private function extractPdfText(ResumeRevision $revision): string
    {
        $disk = Storage::disk('local');
        if (! preg_match('#^resumes/[0-9]+/[a-f0-9-]+\.pdf$#i', $revision->file_path)
            || ! $disk->exists($revision->file_path) || $disk->size($revision->file_path) > 5242880) {
            throw new RuntimeException('invalid_private_pdf');
        }
        // Kiểm tra hash để bảo đảm đang phân tích đúng PDF của revision đã lưu.
        $pdf = $disk->get($revision->file_path);
        if (! hash_equals($revision->file_hash, hash('sha256', $pdf))) {
            throw new RuntimeException('pdf_hash_mismatch');
        }
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(16 * 1024 * 1024);
        $config->setMaxNestingDepth(100);
        $document = (new Parser([], $config))->parseContent($pdf);
        $text = '';
        foreach ($document->getPages() as $page) {
            $text .= ' '.$page->getText();
            if (mb_strlen($text) >= 16000) {
                break;
            }
        }
        $text = mb_substr($this->ollama->normalize($text), 0, max(1000, min(16000, config('ai.max_characters'))));
        if (mb_strlen($text) < 20) {
            throw new RuntimeException('no_extractable_text');
        }

        return $text;
    }

    /**
     * Ghi nhận lỗi queue khi token vẫn thuộc lượt phân tích hiện tại.
     */
    public function queueFailed(int $id, string $token): void
    {
        $this->locked($id, function ($resume, $revision) use ($token) {
            if ($this->eligible($resume, $revision, $token)
                && ! in_array($revision->parsing_status, [ResumeParsingStatus::Completed, ResumeParsingStatus::Failed], true)) {
                $revision->forceFill([
                    'parsing_status' => 'failed',
                    'error_code' => $revision->error_code ?? 'queue_failed',
                    'error_message' => 'Chưa hoàn tất phân tích. Bạn có thể thử lại.',
                    'lease_expires_at' => null,
                    'next_retry_at' => null,
                ])->save();
            }
        });
    }

    /**
     * Ngăn kết quả cũ cập nhật CV đã thay PDF, xóa mềm hoặc yêu cầu thử lại.
     */
    private function eligible(Resume $resume, ResumeRevision $revision, string $token): bool
    {
        return ! $resume->trashed() && $resume->current_revision === $revision->revision && $revision->processing_token === $token;
    }

    /**
     * Khóa CV trước revision để các thao tác đồng thời dùng cùng thứ tự khóa.
     */
    private function locked(int $id, callable $callback): mixed
    {
        return DB::transaction(function () use ($id, $callback) {
            $reference = ResumeRevision::find($id);
            if (! $reference) {
                return null;
            }
            // Luôn khóa CV trước revision, thống nhất thứ tự với thao tác thay PDF và thử lại.
            $resume = Resume::withTrashed()->lockForUpdate()->findOrFail($reference->resume_id);
            $revision = ResumeRevision::lockForUpdate()->findOrFail($id);

            return $callback($resume, $revision);
        });
    }
}
