<?php

namespace App\Policies;

use App\Enums\CompanyStatus;
use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function view(?User $user, Job $job): bool
    {
        if ($user && $user->status !== UserStatus::Active) {
            return false;
        }
        if ($user && ($this->moderate($user, $job) || $this->owns($user, $job))) {
            return true;
        }

        return $this->viewPublic($user, $job);
    }

    public function viewPublic(?User $user, Job $job): bool
    {
        $version = $job->publishedVersion;
        $company = $job->company;

        return (! $user || $user->status === UserStatus::Active)
            && $job->status === JobStatus::Open
            && $job->published_version_id !== null
            && $version?->id === $job->published_version_id
            && $version && $version->job_id === $job->id
            && $version->review_status === JobReviewStatus::Approved
            && $version->application_deadline->format('Y-m-d') >= now('Asia/Ho_Chi_Minh')->format('Y-m-d')
            && $job->expires_at && $job->expires_at->isFuture()
            && $company?->status === CompanyStatus::Active
            && $company->recruiter?->status === UserStatus::Active
            && $company->recruiter?->role === UserRole::Recruiter;
    }

    public function create(User $user, ?Company $company = null): bool
    {
        $company ??= $user->company;

        return $user->status === UserStatus::Active && $user->role === UserRole::Recruiter
            && $company && $company->recruiter_id === $user->id && $company->status === CompanyStatus::Active;
    }

    public function update(User $user, Job $job): bool
    {
        return $this->owns($user, $job) && $job->company->status === CompanyStatus::Active
            && ! in_array($job->status, [JobStatus::Blocked, JobStatus::Closed], true);
    }

    public function moderate(User $user, Job $job): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Admin;
    }

    public function delete(User $user, Job $job): bool
    {
        return false;
    }

    private function owns(User $user, Job $job): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Recruiter
            && $job->company?->recruiter_id === $user->id;
    }
}
