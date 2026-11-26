<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\SavedJob;
use App\Models\User;
use App\Support\JobInput;
use App\Support\Provinces;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavedJobService
{
    public function setSaved(User $actor, int $jobId, bool $saved): void
    {
        DB::transaction(function () use ($actor, $jobId, $saved) {
            if (! $saved) {
                $user = User::lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($user)->authorize('viewAny', SavedJob::class);
                SavedJob::where('candidate_id', $user->id)->where('job_id', $jobId)->delete();

                return;
            }

            $initial = Job::with('company')->findOrFail($jobId);
            // Match the lock order used by applications and moderation.
            $users = User::whereIn('id', [$actor->id, $initial->company->recruiter_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $user = $users->get($actor->id);
            Gate::forUser($user)->authorize('viewAny', SavedJob::class);
            $company = Company::lockForUpdate()->findOrFail($initial->company_id);
            $job = Job::lockForUpdate()->findOrFail($jobId);
            $company->setRelation('recruiter', $users->get($company->recruiter_id));
            $job->setRelation('company', $company);
            if (! Gate::forUser($user)->allows('viewPublic', $job)) {
                throw ValidationException::withMessages(['saved' => 'Tin không còn đủ điều kiện để lưu mới.']);
            }
            if (! SavedJob::where('candidate_id', $user->id)->where('job_id', $jobId)->exists()) {
                $record = new SavedJob;
                $record->candidate_id = $user->id;
                $record->job_id = $jobId;
                $record->save();
            }
        }, 3);
    }

    public function listing(User $user)
    {
        Gate::forUser($user)->authorize('viewAny', SavedJob::class);

        return SavedJob::where('candidate_id', $user->id)
            ->with(['job.company.recruiter', 'job.publishedVersion'])
            ->latest('created_at')->latest('id')->paginate(10)
            ->through(fn (SavedJob $saved) => [
                'job_id' => $saved->job_id, 'saved_at' => $saved->created_at,
                'card' => $this->metadata($saved->job, $user),
            ]);
    }

    private function metadata(Job $job, User $user): ?array
    {
        $version = $job->publishedVersion;
        $company = $job->company;
        // Closed/paused/expired jobs expose only approved card metadata, never full content.
        if (! in_array($job->status, [JobStatus::Open, JobStatus::Closed, JobStatus::Paused], true)
            || ! $version || $version->job_id !== $job->id || $version->review_status !== JobReviewStatus::Approved
            || $company?->status !== CompanyStatus::Active
            || $company->recruiter?->status !== UserStatus::Active
            || $company->recruiter?->role !== UserRole::Recruiter) {
            return null;
        }
        $canView = Gate::forUser($user)->allows('viewPublic', $job);
        $status = $job->status->value;
        if ($status === 'open' && ! $canView) {
            $status = 'expired';
        }

        return [
            'real' => true, 'id' => $job->id, 'title' => $version->title,
            'company' => $company->name, 'logo_url' => app(CompanyService::class)->publicData($company)['logo_url'],
            'position' => $version->position, 'status' => $status, 'can_view' => $canView,
            'salary_min' => $version->salary_min === null ? null : bcdiv($version->salary_min, '1000000', 2),
            'salary_max' => $version->salary_max === null ? null : bcdiv($version->salary_max, '1000000', 2),
            'salary_negotiable' => $version->salary_negotiable,
            'location' => Provinces::ALL[$version->province_code] ?? $version->location,
            'location_detail' => $version->full_location, 'level' => $version->level?->name,
            'mode' => JobInput::MODES[$version->work_mode] ?? '', 'skills' => [],
            'deadline' => $version->application_deadline,
        ];
    }
}
