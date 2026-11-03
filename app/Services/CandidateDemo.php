<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Session-scoped UI preview. No database records, AI calls or email deliveries. */
class CandidateDemo
{
    public function state(): array
    {
        return session('candidate_preview', [
            'active' => false,
            'resumes' => [],
            'saved' => [],
            'applications' => [],
            'notifications' => [],
            'profile' => [
                'name' => 'Ứng viên minh họa',
                'email' => 'ungvien@example.com',
                'phone' => '',
                'location' => '',
                'profession' => '',
                'experience' => '',
                'bio' => '',
            ],
        ]);
    }

    public function update(string $key, mixed $value): void
    {
        $state = $this->state();
        $state[$key] = $value;
        session(['candidate_preview' => $state]);
    }

    public function resume(string $id): ?array
    {
        return collect($this->state()['resumes'])->firstWhere('id', $id);
    }

    public function mainResume(): ?array
    {
        return collect($this->state()['resumes'])->firstWhere('main', true);
    }

    public function upload(UploadedFile $file, ?string $replacementId = null): void
    {
        $resumes = $this->state()['resumes'];

        if ($replacementId === null && count($resumes) >= 10) {
            $this->reject('pdf', 'Chỉ được lưu tối đa 10 CV.');
        }

        $contents = $file->get();

        if (! str_starts_with($contents, '%PDF-') || ! str_contains(substr($contents, -2048), '%%EOF')) {
            $this->reject('pdf', 'Tệp không có cấu trúc PDF hợp lệ.');
        }

        $previous = $replacementId ? $this->resume($replacementId) : null;
        abort_if($replacementId && ! $previous, 404);
        $path = $file->store('candidate-preview', 'local');
        $resume = [
            'id' => $replacementId ?? (string) Str::uuid(),
            'name' => $previous['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'created_at' => $previous['created_at'] ?? now()->format('d/m/Y H:i'),
            'status' => 'pending',
            'main' => $previous['main'] ?? count($resumes) === 0,
            'skills' => [],
            'recognized' => [],
        ];

        if ($previous) {
            Storage::disk('local')->delete($previous['path']);
            $resumes = array_values(array_filter($resumes, fn ($item) => $item['id'] !== $replacementId));
        }

        $resumes[] = $resume;
        $this->update('resumes', $resumes);
    }

    public function changeResume(string $id, string $action, array $input): void
    {
        $resume = $this->resume($id);
        abort_unless($resume, 404);
        $resumes = $this->state()['resumes'];

        if ($action === 'delete') {
            $others = array_values(array_filter($resumes, fn ($item) => $item['id'] !== $id));

            if ($resume['main'] && count($others) > 0) {
                $replacement = collect($others)->firstWhere('id', $input['replacement'] ?? '');

                if (! $replacement) {
                    $this->reject('replacement', 'Hãy chọn CV chính thay thế trước khi xóa.');
                }

                foreach ($others as &$item) {
                    $item['main'] = $item['id'] === $replacement['id'];
                }
            }

            Storage::disk('local')->delete($resume['path']);
            $this->update('resumes', $others);

            return;
        }

        foreach ($resumes as &$item) {
            if ($action === 'main') {
                $item['main'] = $item['id'] === $id;
            }

            if ($item['id'] !== $id) {
                continue;
            }

            if ($action === 'rename') {
                $item['name'] = trim($input['name']);
            }

            if ($action === 'state') {
                $item['status'] = $input['status'];
            }

            if ($action === 'sample') {
                $item['recognized'] = ['php', 'Laravel', 'mysql', 'REST API'];
                $item['skills'] = ['PHP', 'Laravel', 'MySQL', 'REST API'];
                $item['status'] = 'needs_review';
            }

            if ($action === 'skills') {
                $skills = array_filter(array_map('trim', explode(',', $input['skills'])));
                $standardNames = ['php' => 'PHP', 'mysql' => 'MySQL', 'rest api' => 'REST API', 'laravel' => 'Laravel'];
                $normalized = [];

                foreach ($skills as $skill) {
                    $key = mb_strtolower($skill);
                    $normalized[$key] = $standardNames[$key] ?? $skill;
                }

                if ($normalized === []) {
                    $this->reject('skills', 'Cần ít nhất một kỹ năng để xác nhận.');
                }

                $item['skills'] = array_values($normalized);
                $item['status'] = 'completed';
            }
        }

        $this->update('resumes', $resumes);
    }

    public function toggleSaved(int $jobId): void
    {
        $saved = $this->state()['saved'];

        if (in_array($jobId, $saved, true)) {
            $saved = array_values(array_diff($saved, [$jobId]));
        } else {
            $saved[] = $jobId;
        }

        $this->update('saved', $saved);
    }

    public function applicationError(?array $job): ?string
    {
        if (! $job || $job['status'] !== 'open' || $job['deadline']->isPast()) {
            return 'Tin đã đóng, hết hạn hoặc không khả dụng.';
        }

        $attempts = collect($this->state()['applications'])->where('job_id', $job['id']);

        if ($attempts->count() >= 2) {
            return 'Bạn đã dùng hết hai lượt ứng tuyển.';
        }

        if ($attempts->isNotEmpty() && $attempts->last()['status'] !== 'withdrawn') {
            return 'Bạn đã có hồ sơ ứng tuyển cho công việc này.';
        }

        return null;
    }

    public function apply(array $job, string $resumeId, string $letter): void
    {
        $error = $this->applicationError($job);

        if ($error) {
            $this->reject('application', $error);
        }

        $resume = $this->resume($resumeId);

        if (! $resume || ! Storage::disk('local')->exists($resume['path'])) {
            $this->reject('resume', 'Vui lòng chọn một CV PDF hợp lệ.');
        }

        $applications = $this->state()['applications'];
        $application = [
            'id' => (string) Str::uuid(),
            'job_id' => $job['id'],
            'job_title' => $job['title'],
            'company' => $job['company'],
            'resume_name' => $resume['name'],
            'filename' => $resume['filename'],
            'letter' => $letter,
            'attempt' => collect($applications)->where('job_id', $job['id'])->count() + 1,
            'status' => 'applied',
            'date' => now()->format('d/m/Y H:i'),
            'timeline' => [['status' => 'applied', 'date' => now()->format('d/m/Y H:i')]],
        ];
        $applications[] = $application;
        $this->update('applications', $applications);
        $this->notify($application);
    }

    public function withdraw(string $id): void
    {
        $applications = $this->state()['applications'];
        $applicationIndex = array_search($id, array_column($applications, 'id'), true);
        abort_if($applicationIndex === false, 404);
        $application = $applications[$applicationIndex];

        if (! in_array($application['status'], ['applied', 'reviewing', 'interview'], true)) {
            $this->reject('application', 'Không thể rút hồ sơ ở trạng thái hiện tại.');
        }

        $application['status'] = 'withdrawn';
        $application['timeline'][] = ['status' => 'withdrawn', 'date' => now()->format('d/m/Y H:i')];
        $applications[$applicationIndex] = $application;
        $this->update('applications', $applications);
        $this->notify($application);
    }

    public function notify(array $application): void
    {
        $notifications = $this->state()['notifications'];
        array_unshift($notifications, [
            'id' => (string) Str::uuid(),
            'application_id' => $application['id'],
            'title' => $application['job_title'],
            'status' => $application['status'],
            'date' => now()->format('d/m/Y H:i'),
            'read' => false,
        ]);
        $this->update('notifications', $notifications);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
