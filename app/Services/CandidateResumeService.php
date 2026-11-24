<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\ResumeRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class CandidateResumeService
{
    public function upload(User $actor, UploadedFile $file, ?string $publicId = null): Resume
    {
        Gate::forUser($actor)->authorize('create', Resume::class);
        $contents = $file->get();
        try {
            if ($file->getSize() < 1 || $file->getSize() > 5242880 || $file->getMimeType() !== 'application/pdf'
                || ! str_starts_with($contents, '%PDF-') || ! str_contains(substr($contents, -2048), '%%EOF')) {
                throw new \RuntimeException('Invalid PDF');
            }
            // Upload chỉ kiểm tra cấu trúc PDF; trích xuất văn bản và gọi AI được xử lý nền.
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(16 * 1024 * 1024);
            $config->setMaxNestingDepth(100);
            if (count((new Parser([], $config))->parseContent($contents)->getPages()) === 0) {
                throw new \RuntimeException('PDF has no pages');
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['pdf' => 'PDF không hợp lệ, bị hỏng hoặc được mã hóa. Hãy chọn PDF khác, tối đa 5 MB.']);
        }
        $path = 'resumes/'.$actor->id.'/'.Str::uuid().'.pdf';
        try {
            return DB::transaction(function () use ($actor, $file, $publicId, $contents, $path) {
                $user = User::lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($user)->authorize('create', Resume::class);
                $resumes = Resume::forCandidate($user->id)->orderBy('id')->lockForUpdate()->get();
                if ($publicId) {
                    $resume = $resumes->firstWhere('public_id', $publicId);
                    abort_unless($resume, 404);
                    Gate::forUser($user)->authorize('update', $resume);
                    $resume->current_revision++;
                } else {
                    if ($resumes->count() >= 10) {
                        throw ValidationException::withMessages(['pdf' => 'Bạn chỉ được lưu tối đa 10 CV đang hoạt động.']);
                    }
                    $resume = new Resume;
                    $resume->candidate_id = $user->id;
                    $resume->display_name = mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 100) ?: 'CV';
                    $resume->current_revision = 1;
                    $resume->is_primary = $resumes->isEmpty();
                }
                if (! Storage::disk('local')->put($path, $contents)) {
                    throw new \RuntimeException('Cannot store private PDF');
                }
                $resume->save();
                $revision = new ResumeRevision;
                $revision->forceFill([
                    'resume_id' => $resume->id, 'revision' => $resume->current_revision,
                    'file_path' => $path, 'file_hash' => hash('sha256', $contents), 'file_size' => strlen($contents),
                    'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 250),
                    'mime_type' => 'application/pdf', 'parsing_status' => 'pending',
                ])->save();
                $this->invalidateRecommendations($user->id);

                app(AIResumeAnalysisService::class)->enqueue($revision);

                return $resume;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function change(User $actor, string $id, string $action, array $input): void
    {
        DB::transaction(function () use ($actor, $id, $action, $input) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            $resumes = Resume::forCandidate($user->id)->orderBy('id')->lockForUpdate()->get();
            $resume = $resumes->firstWhere('public_id', $id);
            abort_unless($resume, 404);
            Gate::forUser($user)->authorize($action === 'delete' ? 'delete' : 'update', $resume);
            if ($action === 'rename') {
                $resume->display_name = $input['name'];
                $resume->save();

                return;
            }
            if ($action === 'main') {
                if ($resume->is_primary) {
                    return;
                }
                Resume::forCandidate($user->id)->where('is_primary', true)->update(['is_primary' => false]);
                $resume->is_primary = true;
                $resume->save();
            } elseif ($action === 'delete') {
                $replacement = null;
                if ($resume->is_primary && $resumes->count() > 1) {
                    $replacement = $resumes->firstWhere('public_id', $input['replacement'] ?? '');
                    if (! $replacement || $replacement->id === $resume->id) {
                        throw ValidationException::withMessages(['replacement' => 'Hãy chọn CV chính thay thế trước khi xóa.']);
                    }
                }
                $resume->is_primary = false;
                $resume->save();
                $resume->delete();
                if ($replacement) {
                    $replacement->is_primary = true;
                    $replacement->save();
                }
            }
            $this->invalidateRecommendations($user->id);
        });
    }

    private function invalidateRecommendations(int $candidateId): void
    {
        CandidateProfile::where('user_id', $candidateId)->increment('recommendation_generation');
    }
}
