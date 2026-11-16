<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $input, Request $request): User
    {
        $role = UserRole::tryFrom($input['role']);
        if (! in_array($role, [UserRole::Candidate, UserRole::Recruiter], true)) {
            throw ValidationException::withMessages(['role' => 'Loại tài khoản không hợp lệ.']);
        }

        try {
            $user = DB::transaction(function () use ($input, $role) {
                $user = new User;
                $user->name = $input['name'];
                $user->email = $input['email'];
                $user->password = Hash::make($input['password']);
                $user->role = $role;
                $user->status = UserStatus::Active;
                $user->last_login_at = now();
                $user->save();

                if ($role === UserRole::Candidate) {
                    $profile = new CandidateProfile;
                    $profile->user_id = $user->id;
                    $profile->save();
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'Email đã được sử dụng.']);
        }

        $request->session()->invalidate();
        Auth::login($user, false);
        $request->session()->regenerate();

        return $user;
    }

    public function login(array $input, Request $request): User
    {
        $authenticated = Auth::attempt([
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => UserStatus::Active->value,
        ], false);

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'Không thể đăng nhập bằng thông tin này.']);
        }

        $user = Auth::user();
        $stillActive = DB::transaction(function () use ($user) {
            $account = User::query()->lockForUpdate()->find($user->id);
            if (! $account || $account->status !== UserStatus::Active) {
                return false;
            }
            $account->last_login_at = now();
            $account->save();

            return true;
        });
        if (! $stillActive) {
            $this->logout($request);
            throw ValidationException::withMessages(['email' => 'Không thể đăng nhập bằng thông tin này.']);
        }
        // Drop previews from the previous session; never use them as an identity.
        $request->session()->forget(['candidate_preview', 'company_profile_preview', 'company_profile_owner', 'url.intended', 'google_flow', 'google_pending', 'state']);
        $request->session()->regenerate();

        return $user;
    }

    public function logout(Request $request): void
    {
        $user = Auth::user();
        if ($user && $user->remember_token !== null) {
            User::whereKey($user->id)->update(['remember_token' => null]);
            $user->remember_token = null;
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function changePassword(User $user, array $input, Request $request): void
    {
        DB::transaction(function () use ($user, $input) {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($account->status !== UserStatus::Active || ! Hash::check($input['current_password'], $account->password)) {
                throw ValidationException::withMessages(['current_password' => 'Không thể đổi mật khẩu bằng thông tin này.']);
            }
            $account->password = Hash::make($input['password']);
            $account->remember_token = null;
            $account->save();
            // This project uses the database session driver. Revoke every device.
            DB::table('sessions')->where('user_id', $account->id)->delete();
        });

        // Prevent the guard's cached instance from rotating a legacy remember token.
        $user->remember_token = null;
        $this->logout($request);
    }

    public function destination(User $user): string
    {
        return match ($user->role) {
            UserRole::Candidate => 'candidate.profile',
            UserRole::Recruiter => 'recruiter.dashboard',
            UserRole::Admin => 'admin.dashboard',
        };
    }
}
