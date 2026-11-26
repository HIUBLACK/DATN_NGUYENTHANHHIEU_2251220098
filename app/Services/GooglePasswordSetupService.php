<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GooglePasswordSetupService
{
    public static function eligible(?User $user): bool
    {
        return $user && $user->status === UserStatus::Active
            && $user->has_local_password === false && filled($user->google_subject);
    }

    public static function accountRoute(User $user): string
    {
        return match ($user->role) {
            UserRole::Candidate => 'candidate.settings',
            UserRole::Recruiter => 'recruiter.account',
            UserRole::Admin => 'admin.account',
        };
    }

    public function start(Request $request): RedirectResponse
    {
        $this->clear($request);
        abort_unless(self::eligible($request->user()), 403);
        if (! GoogleAuthService::configured()) {
            return $this->failure($request);
        }
        $request->session()->put('google_setup_flow', $this->proof($request, $request->user()));
        try {
            return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
        } catch (Throwable $exception) {
            return $this->failure($request);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('google_setup_flow');
        $request->session()->forget('google_setup_grant');
        $user = $request->user();
        if (! $this->validProof($request, $user, $flow) || $request->has('error')
            || ! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->failure($request);
        }
        try {
            // Fresh OAuth state được Socialite kiểm tra; không dùng kết quả login cũ.
            $google = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            return $this->failure($request);
        }
        if (! is_string($google->getId()) || ! hash_equals($user->google_subject, $google->getId())
            || ($google->user['email_verified'] ?? false) !== true) {
            return $this->failure($request);
        }
        $user = $user->fresh();
        if (! $this->validProof($request, $user, $flow)) {
            return $this->failure($request);
        }
        $request->session()->put('google_setup_grant', $this->proof($request, $user));

        return redirect()->route(self::accountRoute($user))->with('status', 'Đã xác thực lại Google. Vui lòng thiết lập mật khẩu trong 10 phút.');
    }

    public function hasGrant(Request $request): bool
    {
        return $this->validProof($request, $request->user(), $request->session()->get('google_setup_grant'));
    }

    public function complete(Request $request, string $password): RedirectResponse
    {
        $grant = $request->session()->pull('google_setup_grant');
        $request->session()->forget(['google_setup_flow', 'state']);
        DB::transaction(function () use ($request, $password, $grant) {
            $user = User::lockForUpdate()->find($request->user()->id);
            if (! $this->validProof($request, $user, $grant)) {
                throw ValidationException::withMessages(['password_setup' => 'Quyền thiết lập mật khẩu không còn hợp lệ. Vui lòng xác thực lại Google.']);
            }
            $user->password = Hash::make($password);
            $user->has_local_password = true;
            $user->remember_token = null;
            $user->save();
            Password::broker('users')->deleteToken($user);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        app(AuthService::class)->logout($request);

        return redirect()->route('login')->with('notice', 'Đã thiết lập mật khẩu. Vui lòng đăng nhập lại bằng email/mật khẩu hoặc Google.');
    }

    private function proof(Request $request, User $user): array
    {
        return [
            'user_id' => $user->id,
            'subject' => $user->google_subject,
            'session_id' => $request->session()->getId(),
            'password_hash' => hash('sha256', $user->password),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];
    }

    private function validProof(Request $request, ?User $user, mixed $proof): bool
    {
        return self::eligible($user) && is_array($proof)
            && ($proof['user_id'] ?? null) === $user->id
            && ($proof['subject'] ?? null) === $user->google_subject
            && ($proof['session_id'] ?? null) === $request->session()->getId()
            && ($proof['password_hash'] ?? null) === hash('sha256', $user->password)
            && ($proof['expires_at'] ?? 0) > now()->timestamp;
    }

    private function clear(Request $request): void
    {
        $request->session()->forget(['google_flow', 'google_pending', 'google_setup_flow', 'google_setup_grant', 'state']);
    }

    private function failure(Request $request): RedirectResponse
    {
        $this->clear($request);
        $route = $request->user() ? self::accountRoute($request->user()) : 'login';

        return redirect()->route($route)->withErrors(['password_setup' => 'Không thể xác thực lại Google. Vui lòng bắt đầu lại với đúng tài khoản.']);
    }
}
