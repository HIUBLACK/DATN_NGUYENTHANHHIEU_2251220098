<?php

namespace Tests\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GooglePasswordSetupTest extends TestCase
{
    private MockHandler $http;

    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use isolated MariaDB runner.');
        }
        DB::beginTransaction();
        config(['services.google' => ['client_id' => 'fake-client', 'client_secret' => 'fake-secret', 'redirect' => 'http://localhost/auth/google/callback']]);
        $this->http = new MockHandler;
        Socialite::shouldReceive('driver')->with('google')->andReturnUsing(function () {
            $provider = new GoogleProvider(request(), 'fake-client', 'fake-secret', config('services.google.redirect'));
            $provider->setHttpClient(new Client(['handler' => HandlerStack::create($this->http)]));

            return $provider;
        });
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function account(?bool $local = false, UserRole $role = UserRole::Candidate): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Setup test', 'email' => uniqid().'@test.invalid',
            'password' => 'OldPassword123', 'role' => $role, 'status' => UserStatus::Active,
            'google_subject' => uniqid('subject'), 'has_local_password' => $local,
        ])->save();

        return $user;
    }

    private function begin(User $user): string
    {
        $response = $this->actingAs($user)->post(route('password.setup.google'));
        $response->assertRedirect();
        $this->assertStringContainsString('prompt=select_account', $response->headers->get('Location'));
        $this->withCookie(config('session.cookie'), session()->getId());

        return session('state');
    }

    private function verify(string $state, string $subject)
    {
        $this->http->append(
            new Response(200, [], json_encode(['access_token' => 'fake-token', 'token_type' => 'Bearer'])),
            new Response(200, [], json_encode(['sub' => $subject, 'email' => 'verified@test.invalid', 'email_verified' => true])),
        );

        return $this->get(route('google.callback', ['state' => $state, 'code' => 'fake-code']));
    }

    private function submit(string $password = 'NewPassword123')
    {
        return $this->post(route('password.setup.complete'), ['password' => $password, 'password_confirmation' => $password, 'user_id' => 99999, 'has_local_password' => false]);
    }

    public function test_setup_requires_fresh_google_then_sets_password_and_revokes_every_session(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get(route('candidate.settings'))->assertSee('Thiết lập mật khẩu')->assertDontSee('name="current_password"', false);
        $this->submit()->assertSessionHasErrors('password_setup');
        $state = $this->begin($user);
        $this->assertNull(session('google_flow'));
        $this->verify($state, $user->google_subject)->assertRedirect(route('candidate.settings'));
        $this->get(route('candidate.settings'))->assertSee('Lưu mật khẩu')->assertDontSee('name="current_password"', false);
        $grant = session('google_setup_grant');
        $this->assertNotNull($grant);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        Password::broker('users')->createToken($user);
        $this->submit()->assertRedirect(route('login'));
        $this->assertGuest();
        $fresh = $user->fresh();
        $this->assertTrue($fresh->has_local_password);
        $this->assertTrue(Hash::check('NewPassword123', $fresh->password));
        $this->assertSame($user->email, $fresh->email);
        $this->assertSame($user->role, $fresh->role);
        $this->assertSame($user->status, $fresh->status);
        $this->assertSame($user->google_subject, $fresh->google_subject);
        $this->assertNull($fresh->remember_token);
        $this->assertNull(session('google_setup_grant'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'NewPassword123'])->assertRedirect(route('candidate.profile'));
        $this->withSession(['google_setup_grant' => $grant]);
        $this->submit('ReplayPassword123')->assertForbidden();
        $this->get(route('candidate.settings'))->assertSee('name="current_password"', false)->assertDontSee('Xác thực lại Google');
        $this->post(route('logout'));
        $this->get(route('google.redirect', ['context' => 'login']))->assertRedirect();
        $this->verify(session('state'), $user->google_subject)->assertRedirect(route('candidate.profile'));
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertTrue($user->fresh()->has_local_password);
    }

    public function test_local_and_unknown_accounts_cannot_bypass_current_password(): void
    {
        foreach ([true, null] as $flag) {
            $user = $this->account($flag);
            $this->actingAs($user)->post(route('password.setup.google'))->assertForbidden();
            $this->submit()->assertForbidden();
            $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
            $this->assertSame($flag, $user->fresh()->has_local_password);
            $this->post(route('logout'));
        }
    }

    public function test_wrong_subject_invalid_state_and_replayed_callback_never_grant_setup(): void
    {
        $user = $this->account();
        $this->verify($this->begin($user), 'other-google')->assertSessionHasErrors('password_setup');
        $this->assertNull(session('google_setup_grant'));
        $this->begin($user);
        $this->get(route('google.callback', ['state' => 'wrong', 'code' => 'fake']))->assertSessionHasErrors('password_setup');
        $this->assertNull(session('google_setup_grant'));
        $state = $this->begin($user);
        $this->verify($state, $user->google_subject)->assertSessionHasNoErrors();
        $this->get(route('google.callback', ['state' => $state, 'code' => 'fake']))->assertSessionHasErrors('google');
        $this->assertNull(session('google_setup_grant'));
        $this->submit()->assertSessionHasErrors('password_setup');
    }

    public function test_flow_and_grant_expire_after_ten_minutes(): void
    {
        $user = $this->account();
        $state = $this->begin($user);
        $this->travel(11)->minutes();
        $this->get(route('google.callback', ['state' => $state, 'code' => 'fake']))->assertSessionHasErrors('password_setup');
        $this->travelBack();
        $this->verify($this->begin($user), $user->google_subject);
        $this->travel(11)->minutes();
        $this->submit()->assertSessionHasErrors('password_setup');
        $this->assertFalse($user->fresh()->has_local_password);
        $this->travelBack();
    }

    public function test_account_switch_or_session_rotation_invalidates_setup(): void
    {
        $user = $this->account();
        $other = $this->account();
        $other->password = $user->password;
        $other->save();
        $state = $this->begin($user);
        $this->actingAs($other)->get(route('google.callback', ['state' => $state, 'code' => 'fake']))->assertSessionHasErrors('password_setup');
        $this->verify($this->begin($user), $user->google_subject);
        session()->regenerate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->submit()->assertSessionHasErrors('password_setup');
        $this->assertFalse($user->fresh()->has_local_password);
        $this->assertFalse($other->fresh()->has_local_password);
    }

    public function test_locked_user_and_flag_changed_after_reauth_are_rejected(): void
    {
        $user = $this->account();
        $this->verify($this->begin($user), $user->google_subject);
        User::whereKey($user->id)->update(['has_local_password' => true]);
        $this->submit()->assertForbidden();
        User::whereKey($user->id)->update(['has_local_password' => false]);
        $this->verify($this->begin($user->fresh()), $user->google_subject);
        User::whereKey($user->id)->update(['status' => 'locked']);
        $this->submit()->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse($user->fresh()->has_local_password);
    }

    public function test_setup_is_csrf_protected_and_weak_password_does_not_consume_grant(): void
    {
        $user = $this->account();
        $this->verify($this->begin($user), $user->google_subject);
        $this->submit('weak')->assertSessionHasErrors('password');
        $this->assertNotNull(session('google_setup_grant'));
        $this->app['env'] = 'local';
        $this->post(route('password.setup.google'))->assertStatus(419);
        $this->submit()->assertStatus(419);
        $this->assertFalse($user->fresh()->has_local_password);
    }

    public function test_password_classification_migration_backfills_only_unlinked_accounts(): void
    {
        // DDL diễn tập trên test database; khôi phục schema trước teardown và các test sau.
        DB::rollBack();
        $migration = require database_path('migrations/2026_11_17_000000_add_has_local_password_to_users_table.php');
        $migration->down();
        DB::table('users')->insert([
            ['name' => 'Email', 'email' => 'baseline-email@test.invalid', 'password' => Hash::make('Password123'), 'role' => 'candidate', 'status' => 'active', 'google_subject' => null],
            ['name' => 'Google', 'email' => 'baseline-google@test.invalid', 'password' => Hash::make('Password123'), 'role' => 'candidate', 'status' => 'active', 'google_subject' => 'baseline-subject'],
        ]);
        try {
            $migration->up();
            $this->assertTrue(User::where('email', 'baseline-email@test.invalid')->first()->has_local_password);
            $this->assertNull(User::where('email', 'baseline-google@test.invalid')->first()->has_local_password);
            $this->assertFalse((new User)->isFillable('has_local_password'));
            $this->assertSame(32, count(DB::select('SHOW TABLES')));
        } finally {
            DB::table('users')->whereIn('email', ['baseline-email@test.invalid', 'baseline-google@test.invalid'])->delete();
            DB::beginTransaction();
        }
    }

    public function test_classification_check_rejects_values_other_than_null_zero_one(): void
    {
        $user = $this->account();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['has_local_password' => 2]);
    }

    public function test_rollback_is_blocked_after_users_have_password_classification(): void
    {
        $this->account();
        $migration = require database_path('migrations/2026_11_17_000000_add_has_local_password_to_users_table.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove password classification');
        $migration->down();
    }

    public function test_account_pages_share_safe_setup_ui_for_recruiter_and_admin(): void
    {
        foreach (['recruiter' => 'recruiter.account', 'admin' => 'admin.account'] as $role => $route) {
            $user = $this->account(true, UserRole::from($role));
            $this->actingAs($user)->get(route($route))->assertSee('name="current_password"', false)->assertDontSee('Xác thực lại Google');
            User::whereKey($user->id)->update(['has_local_password' => false]);
            $this->get(route($route))->assertSee('Xác thực lại Google')->assertDontSee('name="current_password"', false);
            $this->post(route('logout'));
        }
    }

    public function test_password_changed_after_reauth_invalidates_setup_grant(): void
    {
        $user = $this->account();
        $this->verify($this->begin($user), $user->google_subject);
        $user->password = Hash::make('ChangedElsewhere123');
        $user->save();
        $this->submit()->assertSessionHasErrors('password_setup');
        $this->assertNull(session('google_setup_grant'));
        $this->assertTrue(Hash::check('ChangedElsewhere123', $user->fresh()->password));
        $this->assertFalse($user->fresh()->has_local_password);
    }
}
