<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CandidateProfileService
{
    public function __construct(private CompanyImageStorage $images) {}

    public function update(User $actor, array $input): void
    {
        $newPath = null;
        try {
            DB::transaction(function () use ($actor, $input, &$newPath) {
                $user = User::lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($user)->authorize('viewAny', CandidateProfile::class);
                $profile = CandidateProfile::where('user_id', $user->id)->lockForUpdate()->first();
                if (! $profile) {
                    $profile = new CandidateProfile;
                    $profile->user_id = $user->id;
                }
                Gate::forUser($user)->authorize('update', $profile);
                $oldPath = $profile->avatar_path;
                if (isset($input['avatar'])) {
                    $newPath = $this->images->store($input['avatar'], (string) $user->id, 'avatar', 'candidate-avatars');
                    $profile->avatar_path = $newPath;
                } elseif ($input['remove_avatar'] ?? false) {
                    $profile->avatar_path = null;
                }
                $user->name = $input['name'];
                $user->save();
                foreach (['phone' => 'phone', 'location' => 'location', 'profession' => 'professional_title', 'experience' => 'experience_summary', 'bio' => 'summary'] as $field => $column) {
                    $profile->{$column} = $input[$field] ?? null;
                }
                $profile->save();
                if ($oldPath && $oldPath !== $profile->avatar_path) {
                    DB::afterCommit(fn () => $this->deleteUnusedAvatar($user->id, $oldPath));
                }
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                $this->deleteUnusedAvatar($actor->id, $newPath);
            }
            throw $exception;
        }
    }

    public function ownsAvatar(int $userId, ?string $path): bool
    {
        return is_string($path) && preg_match('#^candidate-avatars/'.$userId.'/avatar/[a-f0-9-]{36}\.(jpg|png|webp)$#D', $path) === 1;
    }

    private function deleteUnusedAvatar(int $userId, string $path): void
    {
        try {
            if ($this->ownsAvatar($userId, $path) && ! CandidateProfile::where('avatar_path', $path)->exists()) {
                if (! Storage::disk('local')->delete($path)) {
                    Log::warning('Candidate avatar cleanup failed.', ['user_id' => $userId]);
                }
            }
        } catch (Throwable) {
            Log::warning('Candidate avatar cleanup failed.', ['user_id' => $userId]);
        }
    }
}
