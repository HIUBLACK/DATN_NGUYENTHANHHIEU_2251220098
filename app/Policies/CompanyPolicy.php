<?php

namespace App\Policies;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(?User $user, Company $company): bool
    {
        if ($user && $user->status !== UserStatus::Active) {
            return false;
        }
        if ($user && ($this->moderate($user, $company) || $this->owns($user, $company))) {
            return true;
        }

        return $this->viewPublic($user, $company);
    }

    public function viewManagement(User $user, Company $company): bool
    {
        return $this->owns($user, $company);
    }

    public function viewPublic(?User $user, Company $company): bool
    {
        return (! $user || $user->status === UserStatus::Active)
            && $company->status === CompanyStatus::Active
            && $company->recruiter?->status === UserStatus::Active
            && $company->recruiter?->role === UserRole::Recruiter;
    }

    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Recruiter;
    }

    public function update(User $user, Company $company): bool
    {
        return $this->owns($user, $company) && $company->status === CompanyStatus::Active;
    }

    public function moderate(User $user, Company $company): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Admin;
    }

    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    private function owns(User $user, Company $company): bool
    {
        return $this->create($user) && $company->recruiter_id === $user->id;
    }
}
