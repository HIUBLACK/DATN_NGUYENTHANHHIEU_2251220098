<?php

namespace App\Services;

use App\Jobs\SendApplicationReceipt;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\ApplicationStatusHistory;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Notifications\ApplicationReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecruiterApplicationService
{
    public const TRANSITIONS = [
        'applied' => ['reviewing', 'rejected'],
        'reviewing' => ['interview', 'rejected'],
        'interview' => ['hired', 'rejected'],
        'hired' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    public function owned(User $user): Builder
    {
        return Application::whereHas('job.company', function (Builder $query) use ($user) {
            $query->where('recruiter_id', $user->id)->where('status', 'active');
        });
    }

    public function changeStatus(User $actor, string $publicId, array $input): void
    {
        DB::transaction(function () use ($actor, $publicId, $input) {
            $application = $this->lockOwned($actor, $publicId);
            Gate::forUser($actor->fresh())->authorize('changeStatus', $application);
            $oldStatus = $application->status->value;
            if ($input['expected_status'] !== $oldStatus
                || $input['updated_at'] !== $application->updated_at->format('Y-m-d H:i:s.u')
                || ! in_array($input['status'], self::TRANSITIONS[$oldStatus], true)) {
                throw ValidationException::withMessages(['status' => 'Trạng thái đã thay đổi hoặc thao tác không hợp lệ. Hãy tải lại hồ sơ.']);
            }
            $application->status = $input['status'];
            $application->save();
            $history = new ApplicationStatusHistory;
            $history->forceFill([
                'application_id' => $application->id,
                'actor_id' => $actor->id,
                'old_status' => $oldStatus,
                'new_status' => $input['status'],
            ])->save();

            $receipt = new ApplicationReceipt($application->public_id, $application->jobVersion->title, $input['status']);
            DB::afterCommit(function () use ($application, $receipt) {
                $this->notifyCandidate($application, $receipt);
            });
        });
    }

    public function saveNote(User $actor, string $publicId, array $input): void
    {
        DB::transaction(function () use ($actor, $publicId, $input) {
            $application = $this->lockOwned($actor, $publicId);
            Gate::forUser($actor->fresh())->authorize('addNote', $application);
            if (! empty($input['note_id'])) {
                $note = $application->notes()->where('recruiter_id', $actor->id)->lockForUpdate()->findOrFail($input['note_id']);
                if ($input['note_updated_at'] !== $note->updated_at->format('Y-m-d H:i:s.u')) {
                    throw ValidationException::withMessages(['content' => 'Ghi chú đã thay đổi. Hãy tải lại trước khi sửa.']);
                }
            } else {
                $note = new ApplicationNote;
                $note->application_id = $application->id;
                $note->recruiter_id = $actor->id;
            }
            $note->content = $input['content'];
            $note->save();
        });
    }

    private function lockOwned(User $actor, string $publicId): Application
    {
        $initial = $this->owned($actor)->where('public_id', $publicId)->firstOrFail();
        // Same order as Candidate apply; withdrawal also locks Candidate before application.
        User::whereIn('id', [$actor->id, $initial->candidate_id])->orderBy('id')->lockForUpdate()->get();
        $company = Company::lockForUpdate()->findOrFail($initial->job->company_id);
        $job = Job::lockForUpdate()->findOrFail($initial->job_id);
        $job->setRelation('company', $company);
        $application = Application::lockForUpdate()->findOrFail($initial->id);
        $application->setRelation('job', $job);

        return $application;
    }

    private function notifyCandidate(Application $application, ApplicationReceipt $receipt): void
    {
        try {
            $application->candidate->notifyNow($receipt, ['database']);
        } catch (Throwable) {
            Log::warning('Application status notification failed.', ['application_id' => $application->id]);
        }
        if ($application->status->value === 'reviewing') {
            return;
        }
        try {
            SendApplicationReceipt::dispatch($application->candidate_id, $receipt)->afterCommit();
        } catch (Throwable) {
            Log::warning('Application status email enqueue failed.', ['application_id' => $application->id]);
        }
    }
}
