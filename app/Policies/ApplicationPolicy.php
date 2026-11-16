<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    public function view(User $user, Application $application): Response
    {
        $allowed = $user->status === UserStatus::Active && (
            $this->candidateOwns($user, $application)
            || $this->recruiterOwns($user, $application)
            || $user->role === UserRole::Admin
        );

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewSnapshot(User $user, Application $application): bool
    {
        return $user->status === UserStatus::Active
            && ($this->candidateOwns($user, $application) || $this->recruiterOwns($user, $application));
    }

    public function viewNotes(User $user, Application $application): bool
    {
        return $user->status === UserStatus::Active && $this->recruiterOwns($user, $application);
    }

    public function addNote(User $user, Application $application): bool
    {
        return $this->viewNotes($user, $application);
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $user->status === UserStatus::Active && $this->candidateOwns($user, $application)
            && $this->isInProgress($application);
    }

    public function changeStatus(User $user, Application $application): bool
    {
        return $user->status === UserStatus::Active && $this->recruiterOwns($user, $application)
            && $this->isInProgress($application);
    }

    private function candidateOwns(User $user, Application $application): bool
    {
        return $user->role === UserRole::Candidate && $application->candidate_id === $user->id;
    }

    private function recruiterOwns(User $user, Application $application): bool
    {
        return $user->role === UserRole::Recruiter
            && $application->job?->company?->recruiter_id === $user->id
            && $application->job->company->status === CompanyStatus::Active;
    }

    private function isInProgress(Application $application): bool
    {
        return in_array($application->status, [ApplicationStatus::Applied, ApplicationStatus::Reviewing, ApplicationStatus::Interview], true);
    }
}
