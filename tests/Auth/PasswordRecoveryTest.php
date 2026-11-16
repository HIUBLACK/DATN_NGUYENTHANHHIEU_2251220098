<?php

namespace Tests\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\PasswordRecoveryNotification;
use App\Services\PasswordRecoveryService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use php tests/Database/models.php --auth.');
        }
        DB::beginTransaction();
        config([
            'auth.password_recovery_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.test.invalid',
            'mail.from.address' => 'sender@test.invalid',
            'app.url' => 'https://viejob.test',
        ]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function account(?string $subject = null): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Recovery Test', 'email' => uniqid().'@test.invalid',
            'password' => 'OldPassword123', 'role' => UserRole::Candidate,
            'status' => UserStatus::Active, 'google_subject' => $subject,
        ])->save();

        return $user;
    }

    private function requestToken(User $user): string
    {
        $this->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertRedirect(route('password.request'))->assertSessionHas('notice', PasswordRecoveryService::MESSAGE);
        $notification = Notification::sent($user, PasswordRecoveryNotification::class)->last();
        $this->assertNotNull($notification);

        return $notification->token;
    }

    private function submit(User $user, string $token, string $password = 'NewPassword123')
    {
        return $this->post(route('password.update'), [
            'email' => $user->email, 'token' => $token,
            'password' => $password, 'password_confirmation' => $password,
            'role' => 'admin', 'status' => 'active', 'redirect' => 'https://evil.invalid',
        ]);
    }

    public function test_pages_mail_and_token_use_framework_without_host_header_redirect_injection(): void
    {
        $user = $this->account();
        $this->get(route('password.request'))->assertOk()->assertSee('Gửi liên kết đặt lại mật khẩu');
        $this->get(route('login'))->assertSee('href="'.route('password.request').'"', false);
        $token = $this->requestToken($user);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
        $notification = Notification::sent($user, PasswordRecoveryNotification::class)->last();
        $message = $notification->toMail($user);
        $this->assertStringStartsWith('https://viejob.test/reset-password/', $message->actionUrl);
        $this->assertStringContainsString($token, $message->actionUrl);
        $this->assertSame('Đặt lại mật khẩu VIEJOB AI', $message->subject);
        $this->assertStringContainsString('60', implode(' ', $message->outroLines));
        $this->get($message->actionUrl)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_unknown_locked_and_existing_email_have_same_neutral_response(): void
    {
        $active = $this->account();
        $locked = $this->account();
        $locked->status = UserStatus::Locked;
        $locked->save();
        foreach ([$active->email, $locked->email, 'absent@test.invalid'] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()->assertSessionHas('notice', PasswordRecoveryService::MESSAGE);
        }
        Notification::assertSentTo($active, PasswordRecoveryNotification::class);
        Notification::assertNotSentTo($locked, PasswordRecoveryNotification::class);
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    public function test_reset_hashes_password_revokes_sessions_preserves_google_and_is_single_use(): void
    {
        $user = $this->account('google-subject');
        $user->remember_token = 'legacy';
        $user->save();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = $this->requestToken($user);
        Log::spy();
        $this->submit($user, $token)->assertRedirect(route('login'));
        $this->assertGuest();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword123', $fresh->password));
        $this->assertSame('google-subject', $fresh->google_subject);
        $this->assertSame($user->role, $fresh->role);
        $this->assertSame($user->email, $fresh->email);
        $this->assertSame(UserStatus::Active, $fresh->status);
        $this->assertNull($fresh->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->submit($user, $token, 'ReplayPassword123')->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'OldPassword123'])->assertSessionHasErrors('email');
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'NewPassword123'])->assertRedirect(route('candidate.profile'));
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_wrong_expired_and_locked_tokens_are_rejected(): void
    {
        $user = $this->account();
        $token = $this->requestToken($user);
        $this->submit($user, 'incorrect-token')->assertSessionHasErrors('email');
        DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
        $this->submit($user, $token)->assertSessionHasErrors('email');
        $token = Password::broker('users')->createToken($user);
        $user->status = UserStatus::Locked;
        $user->save();
        $this->submit($user, $token)->assertSessionHasErrors('email');
        $this->assertSame(UserStatus::Locked, $user->fresh()->status);
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_resend_throttle_and_email_rate_limit_apply_even_for_unknown_email(): void
    {
        $user = $this->account();
        $this->requestToken($user);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('notice', PasswordRecoveryService::MESSAGE);
        Notification::assertSentToTimes($user, PasswordRecoveryNotification::class, 1);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'absent@test.invalid'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'absent@test.invalid'])->assertStatus(429);
    }

    public function test_log_and_failover_mailers_cannot_expose_reset_tokens(): void
    {
        $user = $this->account();
        foreach (['log', 'array', 'failover', 'roundrobin'] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->post(route('password.email'), ['email' => $user->email])->assertStatus(503);
        }
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->get(route('login'))->assertDontSee('Quên mật khẩu?');
    }

    public function test_validation_does_not_flash_token_or_password_and_csrf_is_required(): void
    {
        $user = $this->account();
        $token = $this->requestToken($user);
        $this->withHeader('Referer', 'https://evil.invalid/reset')
            ->submit($user, $token, 'weak')->assertSessionHasErrors('password')
            ->assertRedirect(route('password.reset', ['token' => $token]));
        $this->assertNull(session()->getOldInput('token'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->app['env'] = 'local';
        $this->post(route('password.email'), ['email' => $user->email])->assertStatus(419);
        $this->submit($user, $token)->assertStatus(419);
    }

    public function test_google_only_can_reset_via_email_without_knowing_random_password(): void
    {
        $user = $this->account('google-only-subject');
        $user->password = Hash::make(bin2hex(random_bytes(32)));
        $user->has_local_password = false;
        $user->save();
        $this->submit($user, $this->requestToken($user))->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->has_local_password);
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'NewPassword123'])->assertRedirect(route('candidate.profile'));
        $this->assertSame('google-only-subject', $user->fresh()->google_subject);
    }

    public function test_changing_password_invalidates_previously_issued_reset_link(): void
    {
        $user = $this->account();
        $token = $this->requestToken($user);
        $this->actingAs($user)->post(route('password.change'), [
            'current_password' => 'OldPassword123', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123',
        ])->assertRedirect(route('login'));
        $this->submit($user, $token)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
    }

    public function test_delivery_failure_rolls_back_token_and_logs_only_fixed_message(): void
    {
        $user = $this->account();
        Notification::swap(\Mockery::mock(Dispatcher::class));
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('private-token-and-smtp-password'));
        Log::spy();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('notice', PasswordRecoveryService::MESSAGE)->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        Log::shouldHaveReceived('warning')->once()->with('Password recovery delivery failed; no credentials or token logged.');
        Log::shouldNotHaveReceived('error');
    }

    public function test_admin_and_recruiter_reset_preserve_database_role(): void
    {
        foreach ([UserRole::Admin, UserRole::Recruiter] as $role) {
            $user = $this->account();
            $user->role = $role;
            $user->save();
            $this->submit($user, $this->requestToken($user))->assertRedirect(route('login'));
            $this->assertSame($role, $user->fresh()->role);
            $this->assertGuest();
        }
    }
}
