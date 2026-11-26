<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Company;
use App\Models\Job;
use App\Models\Resume;
use App\Models\User;
use App\Notifications\ApplicationReceipt;
use App\Notifications\RecruiterApplicationNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CandidateApplicationService
{
    public function availability(int $candidateId, int $jobId): array
    {
        $last = Application::where('candidate_id', $candidateId)->where('job_id', $jobId)->orderByDesc('attempt_no')->first();
        $allowed = ! $last || ($last->attempt_no === 1 && $last->status === ApplicationStatus::Withdrawn);

        return ['allowed' => $allowed, 'attempt' => $last ? $last->attempt_no + 1 : 1];
    }

    public function apply(User $actor, int $jobId, array $data): Application
    {
        $path = 'application-snapshots/'.$actor->id.'/'.Str::uuid().'.pdf';
        try {
            $application = DB::transaction(function () use ($actor, $jobId, $data, $path) {
                $initial = Job::with('company')->findOrFail($jobId);
                // Match moderation lock order: users sorted, company, job, version, CV.
                $users = User::whereIn('id', [$actor->id, $initial->company->recruiter_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $candidate = $users->get($actor->id);
                Gate::forUser($candidate)->authorize('viewAny', Resume::class);
                $company = Company::lockForUpdate()->findOrFail($initial->company_id);
                $job = Job::lockForUpdate()->findOrFail($jobId);
                $company->setRelation('recruiter', $users->get($company->recruiter_id));
                $job->setRelation('company', $company);
                $version = $job->publishedVersionQuery()->lockForUpdate()->first();
                $job->setRelation('publishedVersion', $version);
                if (! Gate::forUser($candidate)->allows('viewPublic', $job)) {
                    throw ValidationException::withMessages(['application' => 'Tin không còn đủ điều kiện nhận ứng tuyển.']);
                }
                if ((int) $data['job_version_id'] !== $version->id) {
                    throw ValidationException::withMessages(['application' => 'Tin đã cập nhật. Hãy tải lại và kiểm tra nội dung trước khi nộp.']);
                }
                $last = Application::where('candidate_id', $candidate->id)->where('job_id', $jobId)
                    ->orderByDesc('attempt_no')->lockForUpdate()->first();
                $attempt = $last ? $last->attempt_no + 1 : 1;
                if ($attempt > 2 || ($last && $last->status !== ApplicationStatus::Withdrawn)
                    || (int) $data['attempt_no'] !== $attempt) {
                    throw ValidationException::withMessages(['application' => 'Không thể nộp trùng. Bạn chỉ được ứng tuyển lại một lần sau khi rút lượt đầu.']);
                }
                $resume = Resume::forCandidate($candidate->id)->where('public_id', $data['resume'])->lockForUpdate()->firstOrFail();
                Gate::forUser($candidate)->authorize('view', $resume);
                $revision = $resume->currentRevision()->lockForUpdate()->firstOrFail();
                $disk = Storage::disk('local');
                $contents = $disk->get($revision->file_path);
                if (! is_string($contents) || ! hash_equals($revision->file_hash, hash('sha256', $contents))
                    || strlen($contents) !== $revision->file_size || ! $disk->put($path, $contents)) {
                    throw ValidationException::withMessages(['resume' => 'Không đọc được CV hợp lệ. Hãy tải lại CV.']);
                }
                $application = new Application;
                $application->forceFill([
                    'candidate_id' => $candidate->id, 'job_id' => $job->id, 'job_version_id' => $version->id,
                    'attempt_no' => $attempt, 'resume_id' => $resume->id, 'resume_revision' => $revision->revision,
                    'resume_snapshot_path' => $path, 'resume_snapshot_hash' => $revision->file_hash,
                    'resume_snapshot_filename' => $revision->original_filename, 'resume_snapshot_size' => $revision->file_size,
                    'cover_letter' => $data['letter'] ?? null, 'status' => 'applied', 'applied_at' => now(),
                ])->save();
                $this->recordEvent($candidate, $application, null);

                return $application;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        $this->sendMailAfterCommit($application);

        return $application;
    }

    public function withdraw(User $actor, string $publicId): void
    {
        $application = DB::transaction(function () use ($actor, $publicId) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            $application = Application::where('candidate_id', $user->id)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('withdraw', $application);
            $old = $application->status->value;
            $application->status = ApplicationStatus::Withdrawn;
            $application->withdrawn_at = now();
            $application->save();
            $this->recordEvent($user, $application, $old);

            return $application;
        });
        $this->sendMailAfterCommit($application);
    }

    private function recordEvent(User $user, Application $application, ?string $old): void
    {
        $history = new ApplicationStatusHistory;
        $history->forceFill([
            'application_id' => $application->id, 'actor_id' => $user->id,
            'old_status' => $old, 'new_status' => $application->status->value,
        ])->save();
        $user->notifyNow($this->receipt($application), ['database']);
        $application->job->company->recruiter->notifyNow(new RecruiterApplicationNotice(
            $application->public_id, $application->jobVersion->title, $user->name,
            $application->attempt_no, $application->status->value,
        ), ['database']);
    }

    private function receipt(Application $application): ApplicationReceipt
    {
        return new ApplicationReceipt($application->public_id, $application->jobVersion->title, $application->status->value, $application->job->company->name);
    }

    private function sendMailAfterCommit(Application $application): void
    {
        DB::afterCommit(function () use ($application) {
            // Gửi ngay sau commit; lỗi SMTP không hoàn tác hồ sơ đã lưu.
            try {
                $application->candidate->notifyNow($this->receipt($application), ['mail']);
            } catch (Throwable) {
                Log::warning('Application email delivery failed.', ['application_id' => $application->id, 'status' => $application->status->value]);
            }
        });
    }
}
