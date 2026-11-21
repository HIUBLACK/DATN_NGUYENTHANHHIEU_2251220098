<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class SavedJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Candidate && $user->status === UserStatus::Active;
    }
}
