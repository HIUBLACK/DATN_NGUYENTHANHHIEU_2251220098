<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ResumePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Candidate;
    }

    public function view(User $user, Resume $resume): Response
    {
        $allowed = $this->viewAny($user) && $resume->candidate_id === $user->id && ! $resume->trashed();

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Resume $resume): bool
    {
        return $this->view($user, $resume)->allowed();
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $this->view($user, $resume)->allowed();
    }
}
