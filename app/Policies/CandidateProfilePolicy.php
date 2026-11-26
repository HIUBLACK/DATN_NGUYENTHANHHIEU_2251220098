<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Candidate && $user->status === UserStatus::Active;
    }

    public function update(User $user, CandidateProfile $profile): bool
    {
        return $this->viewAny($user) && $profile->user_id === $user->id;
    }
}
