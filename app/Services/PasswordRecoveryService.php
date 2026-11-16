<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\PasswordRecoveryNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordRecoveryService
{
    public const MESSAGE = 'Nếu email đã được đăng ký, chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu.';

    public static function available(): bool
    {
        return (bool) config('auth.password_recovery_enabled')
            && config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.transport') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.port'))
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && filter_var(config('app.url'), FILTER_VALIDATE_URL);
    }

    public function send(string $email): void
    {
        abort_unless(self::available(), 503, 'Khôi phục mật khẩu qua email chưa được cấu hình.');
        try {
            DB::transaction(function () use ($email) {
                // Cùng khóa User với reset để gửi lại và tiêu thụ token không đua nhau.
                User::where('email', $email)->lockForUpdate()->first();
                Password::broker('users')->sendResetLink([
                    'email' => $email, 'status' => UserStatus::Active->value,
                ], function (User $user, string $token) {
                    $user->notify(new PasswordRecoveryNotification($token));
                });
            });
        } catch (Throwable $exception) {
            // SMTP exception có thể chứa URL/token hoặc credential. Không log nội dung.
            // Transaction rollback token mới; giữ cùng phản hồi cho mọi email.
            Log::warning('Password recovery delivery failed; no credentials or token logged.');
        }
    }

    public function reset(array $input, Request $request): bool
    {
        $resetUser = null;
        $status = DB::transaction(function () use ($input, &$resetUser) {
            $account = User::where('email', $input['email'])->lockForUpdate()->first();
            if (! $account || $account->status !== UserStatus::Active) {
                return Password::INVALID_TOKEN;
            }

            return Password::broker('users')->reset($input + ['status' => UserStatus::Active->value], function (User $user, string $password) use (&$resetUser) {
                $user->password = Hash::make($password);
                $user->has_local_password = true;
                $user->remember_token = null;
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $resetUser = $user;
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            return false;
        }

        // Không Auth::login; xóa phiên/OAuth context hiện tại và bắt đăng nhập lại.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        event(new PasswordReset($resetUser));

        return true;
    }
}
