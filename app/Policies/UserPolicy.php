<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function moderate(User $admin, User $subject): bool
    {
        return $admin->role === UserRole::Admin && $admin->status === UserStatus::Active
            && $admin->id !== $subject->id
            && in_array($subject->role, [UserRole::Candidate, UserRole::Recruiter], true);
    }
}
