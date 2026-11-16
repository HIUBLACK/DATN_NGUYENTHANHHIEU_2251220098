<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthService
{
    public function __construct(private AuthService $auth) {}

    public static function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function start(Request $request, string $context): RedirectResponse
    {
        $this->clear($request);
        if (! self::configured()) {
            return $this->failure($request, 'Đăng nhập Google chưa được cấu hình.', $context);
        }
        $flow = ['context' => $context, 'expires_at' => now()->addMinutes(10)->timestamp];
        if ($context === 'link') {
            $flow['user_id'] = $request->user()->id;
            // Hủy liên kết chờ nếu mật khẩu đã đổi trong lúc đi qua Google.
            $flow['password_hash'] = hash('sha256', $request->user()->password);
        }
        $request->session()->put('google_flow', $flow);
        try {
            return Socialite::driver('google')->redirect();
        } catch (Throwable $exception) {
            // Không log exception OAuth vì có thể chứa code/token/secret.
            return $this->failure($request, 'Không thể kết nối Google. Vui lòng thử lại.', $context);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('google_flow');
        $context = $flow['context'] ?? 'login';
        if (! is_array($flow) || ($flow['expires_at'] ?? 0) < now()->timestamp || ! self::configured()) {
            return $this->failure($request, 'Phiên xác thực Google đã hết hạn. Vui lòng thử lại.');
        }
        if ($request->has('error') || ! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->failure($request, 'Xác thực Google chưa hoàn tất. Vui lòng thử lại.', $context);
        }
        if (($context !== 'link' && $request->user()) || ($context === 'link' && $request->user()?->id !== ($flow['user_id'] ?? null))) {
            return $this->failure($request, 'Phiên tài khoản đã thay đổi. Vui lòng thử lại.', $context);
        }
        try {
            // Socialite tiêu thụ và kiểm tra state; không dùng stateless/userFromToken.
            $google = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            return $this->failure($request, 'Không thể xác minh Google. Vui lòng bắt đầu lại.', $context);
        }
        $subject = $google->getId();
        $email = $google->getEmail();
        if (! is_string($subject) || ! preg_match('/^[\x21-\x7E]{1,255}$/D', $subject)
            || ($google->user['email_verified'] ?? false) !== true
            || ! is_string($email)
            || Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            return $this->failure($request, 'Google chưa cung cấp định danh và email đã xác minh hợp lệ.', $context);
        }
        $email = mb_strtolower(trim($email));
        try {
            if ($context === 'link') {
                $this->link($request, $flow, $subject);
                $this->clear($request);

                return redirect()->route($this->accountRoute($request->user()))->with('status', 'Đã liên kết tài khoản Google.');
            }
            $user = User::where('google_subject', $subject)->first();
            if ($user) {
                return $this->signIn($request, $user);
            }
            if (User::where('email', $email)->exists()) {
                return $this->failure($request, 'Email đã có tài khoản. Hãy đăng nhập bằng phương thức hiện có, rồi liên kết Google trong cài đặt tài khoản.', 'login');
            }
            $request->session()->put('google_pending', Crypt::encryptString(json_encode([
                'subject' => $subject,
                'email' => $email,
                'name' => mb_substr(trim((string) $google->getName()) ?: 'Người dùng Google', 0, 255),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], JSON_THROW_ON_ERROR)));

            return redirect()->route('google.register');
        } catch (ValidationException|UniqueConstraintViolationException $exception) {
            return $this->failure($request, 'Không thể đăng nhập hoặc liên kết Google với tài khoản này.', $context);
        }
    }

    public function pending(Request $request): array
    {
        try {
            $encrypted = $request->session()->get('google_pending');
            $pending = is_string($encrypted) ? json_decode(Crypt::decryptString($encrypted), true) : null;
        } catch (DecryptException $exception) {
            $pending = null;
        }
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $this->clear($request);
            throw ValidationException::withMessages(['google' => 'Phiên đăng ký Google đã hết hạn. Vui lòng bắt đầu lại.']);
        }

        return $pending;
    }

    public function complete(Request $request, string $role): RedirectResponse
    {
        if (! in_array($role, ['candidate', 'recruiter'], true)) {
            throw ValidationException::withMessages(['role' => 'Loại tài khoản không hợp lệ.']);
        }
        try {
            $pending = $this->pending($request);
            $user = DB::transaction(function () use ($pending, $role) {
                if (User::where('email', $pending['email'])->orWhere('google_subject', $pending['subject'])->exists()) {
                    throw ValidationException::withMessages(['google' => 'Tài khoản đã tồn tại. Vui lòng đăng nhập lại.']);
                }
                $user = new User;
                $user->name = $pending['name'];
                $user->email = $pending['email'];
                $user->google_subject = $pending['subject'];
                $user->email_verified_at = now();
                $user->password = Hash::make(bin2hex(random_bytes(32)));
                $user->role = UserRole::from($role);
                $user->status = UserStatus::Active;
                $user->save();
                if ($user->role === UserRole::Candidate) {
                    $profile = new CandidateProfile;
                    $profile->user_id = $user->id;
                    $profile->save();
                }

                return $user;
            });

            return $this->signIn($request, $user);
        } catch (ValidationException|UniqueConstraintViolationException $exception) {
            return $this->failure($request, 'Phiên đăng ký không còn hợp lệ hoặc tài khoản đã tồn tại. Vui lòng bắt đầu lại.', 'register');
        }
    }

    private function link(Request $request, array $flow, string $subject): void
    {
        DB::transaction(function () use ($request, $flow, $subject) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            if ($user->status !== UserStatus::Active
                || ! hash_equals($flow['password_hash'], hash('sha256', $user->password))
                || ($user->google_subject !== null && $user->google_subject !== $subject)
                || User::where('google_subject', $subject)->where('id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages(['google' => 'Không thể liên kết tài khoản này.']);
            }
            $user->google_subject = $subject;
            $user->save();
        });
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        $user = DB::transaction(function () use ($user) {
            $account = User::lockForUpdate()->findOrFail($user->id);
            if ($account->status !== UserStatus::Active) {
                throw ValidationException::withMessages(['google' => 'Không thể đăng nhập tài khoản này.']);
            }
            $account->last_login_at = now();
            $account->save();

            return $account;
        });
        $request->session()->invalidate();
        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route($this->auth->destination($user));
    }

    private function clear(Request $request): void
    {
        $request->session()->forget(['google_flow', 'google_pending', 'state']);
    }

    private function failure(Request $request, string $message, string $context = 'login'): RedirectResponse
    {
        $this->clear($request);
        $route = $request->user() ? $this->accountRoute($request->user()) : ($context === 'register' ? 'register' : 'login');

        return redirect()->route($route)->withErrors(['google' => $message]);
    }

    private function accountRoute(User $user): string
    {
        return match ($user->role) {
            UserRole::Candidate => 'candidate.settings',
            UserRole::Recruiter => 'recruiter.account',
            UserRole::Admin => 'admin.account',
        };
    }
}
