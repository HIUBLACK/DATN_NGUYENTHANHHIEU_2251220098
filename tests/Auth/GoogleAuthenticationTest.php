<?php

namespace Tests\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    private MockHandler $http;

    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use the isolated MariaDB test runner.');
        }
        DB::beginTransaction();
        config(['services.google' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => 'http://localhost:8000/auth/google/callback',
        ]]);
        $this->http = new MockHandler;
        Socialite::shouldReceive('driver')->with('google')->andReturnUsing(function () {
            $provider = new GoogleProvider(request(), 'test-client', 'test-secret', config('services.google.redirect'));
            $provider->setHttpClient(new Client(['handler' => HandlerStack::create($this->http)]));

            return $provider;
        });
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function user(UserRole $role = UserRole::Candidate, ?string $subject = null): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Test account', 'email' => uniqid().'@test.invalid',
            'password' => 'Password123', 'role' => $role, 'status' => UserStatus::Active,
            'google_subject' => $subject,
        ])->save();

        return $user;
    }

    private function begin(string $context = 'login'): string
    {
        $this->get(route('google.redirect', ['context' => $context]))->assertRedirect();

        return session('state');
    }

    private function googleCallback(string $state, string $subject = 'google-123', string $email = 'new@test.invalid', bool $verified = true)
    {
        $this->http->append(
            new Response(200, [], json_encode(['access_token' => 'fake-token', 'token_type' => 'Bearer'])),
            new Response(200, [], json_encode(['sub' => $subject, 'email' => $email, 'email_verified' => $verified, 'name' => 'Người dùng Google'])),
        );

        return $this->get(route('google.callback', ['state' => $state, 'code' => 'fake-code', 'role' => 'admin']));
    }

    public function test_linked_google_login_uses_subject_and_database_role_for_all_roles(): void
    {
        foreach (['candidate' => 'candidate.profile', 'recruiter' => 'recruiter.dashboard', 'admin' => 'admin.dashboard'] as $role => $destination) {
            $user = $this->user(UserRole::from($role), 'google-'.$role);
            $oldSession = session()->getId();
            $this->googleCallback($this->begin(), 'google-'.$role, 'changed@test.invalid')->assertRedirect(route($destination));
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldSession, session()->getId());
            $this->assertNotNull($user->fresh()->last_login_at);
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertNull($user->fresh()->remember_token);
            $this->post(route('logout'));
        }
    }

    public function test_first_login_waits_for_role_and_creates_candidate_only_after_confirmation(): void
    {
        $state = $this->begin('register');
        $this->googleCallback($state)->assertRedirect(route('google.register'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertStringNotContainsString('google-123', session('google_pending'));
        $this->get(route('google.register'))->assertOk()->assertDontSee('value="admin"', false);
        $this->post(route('google.complete'), ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertSame(0, User::count());
        $this->post(route('google.complete'), ['role' => 'candidate', 'email' => 'forged@test.invalid', 'status' => 'locked'])
            ->assertRedirect(route('candidate.profile'));
        $user = User::firstOrFail();
        $this->assertSame('new@test.invalid', $user->email);
        $this->assertSame(UserRole::Candidate, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('google-123', $user->google_subject);
        $this->assertFalse($user->has_local_password);
        $this->assertNotNull($user->candidateProfile);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('unknown', Hash::info($user->password)['algoName']);
        $this->assertArrayNotHasKey('google_subject', $user->toArray());
        $this->post(route('google.complete'), ['role' => 'candidate'])->assertRedirect();
        $this->post(route('logout'));
        $this->get(route('google.callback', ['state' => $state, 'code' => 'fake-code']))->assertRedirect(route('login'));
        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_google_recruiter_registration_creates_no_company_or_candidate_profile(): void
    {
        $this->googleCallback($this->begin())->assertRedirect(route('google.register'));
        $this->post(route('google.complete'), ['role' => 'recruiter'])->assertRedirect(route('recruiter.dashboard'));
        $user = User::firstOrFail();
        $this->assertSame(UserRole::Recruiter, $user->role);
        $this->assertFalse($user->has_local_password);
        $this->assertNull($user->company);
        $this->assertSame(0, CandidateProfile::count());
    }

    public function test_duplicate_email_is_not_auto_linked_or_authenticated(): void
    {
        $user = $this->user();
        $this->googleCallback($this->begin(), 'unlinked', strtoupper($user->email))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_subject);
        $this->assertSame(1, User::count());
        $this->assertNull(session('google_pending'));
    }

    public function test_locked_google_account_cannot_login(): void
    {
        $user = $this->user(UserRole::Recruiter, 'locked-subject');
        $user->status = UserStatus::Locked;
        $user->save();
        $this->googleCallback($this->begin(), 'locked-subject')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_invalid_state_denial_unverified_email_and_expiry_fail_closed(): void
    {
        $this->begin();
        $this->get(route('google.callback', ['state' => 'wrong', 'code' => 'fake']))->assertSessionHasErrors('google');
        $this->assertSame(0, $this->http->count());
        $this->begin();
        $this->get(route('google.callback', ['error' => 'access_denied']))->assertSessionHasErrors('google');
        $this->googleCallback($this->begin(), verified: false)->assertSessionHasErrors('google');
        $state = $this->begin();
        $this->travel(11)->minutes();
        $this->get(route('google.callback', ['state' => $state, 'code' => 'fake']))->assertSessionHasErrors('google');
        $this->assertSame(0, User::count());
        $this->assertGuest();
        $this->travelBack();
    }

    public function test_pending_registration_expires_and_rechecks_email_before_insert(): void
    {
        $this->googleCallback($this->begin());
        $this->travel(11)->minutes();
        $this->post(route('google.complete'), ['role' => 'candidate'])->assertRedirect(route('register'));
        $this->assertSame(0, User::count());
        $this->travelBack();
        $this->googleCallback($this->begin());
        $existing = $this->user();
        $existing->email = 'new@test.invalid';
        $existing->save();
        $this->post(route('google.complete'), ['role' => 'candidate'])->assertSessionHasErrors('google');
        $this->assertSame(1, User::count());
        $this->assertNull($existing->fresh()->google_subject);
        $this->assertGuest();
    }

    public function test_link_requires_password_and_binds_to_current_user_not_callback_input(): void
    {
        $user = $this->user(UserRole::Admin);
        $other = $this->user();
        $this->actingAs($user)->post(route('google.link'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertNull(session('google_flow'));
        $this->post(route('google.link'), ['current_password' => 'Password123', 'user_id' => $other->id])->assertRedirect();
        $this->googleCallback(session('state'), 'admin-linked')->assertRedirect(route('admin.account'));
        $this->assertSame('admin-linked', $user->fresh()->google_subject);
        $this->assertNull($other->fresh()->google_subject);
        $this->assertSame(UserRole::Admin, $user->fresh()->role);
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }

    public function test_cannot_link_subject_owned_by_another_account(): void
    {
        $owner = $this->user(UserRole::Candidate, 'owned-subject');
        $other = $this->user(UserRole::Recruiter);
        $this->actingAs($other)->post(route('google.link'), ['current_password' => 'Password123']);
        $this->googleCallback(session('state'), 'owned-subject')->assertSessionHasErrors('google');
        $this->assertNull($other->fresh()->google_subject);
        $this->assertSame('owned-subject', $owner->fresh()->google_subject);
    }

    public function test_link_and_registration_are_csrf_protected(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('google.complete'), ['role' => 'candidate'])->assertStatus(419);
        $this->actingAs($this->user())->post(route('google.link'), ['current_password' => 'Password123'])->assertStatus(419);
    }

    public function test_google_registration_rolls_back_when_profile_creation_fails(): void
    {
        $this->googleCallback($this->begin());
        CandidateProfile::creating(function () {
            throw new \RuntimeException('Test profile failure');
        });
        try {
            $this->post(route('google.complete'), ['role' => 'candidate'])->assertStatus(500);
            $this->assertSame(0, User::count());
            $this->assertGuest();
        } finally {
            CandidateProfile::flushEventListeners();
        }
    }

    public function test_google_subject_is_unique_and_not_mass_assignable(): void
    {
        $owner = $this->user(UserRole::Candidate, 'unique-subject');
        $other = $this->user();
        $this->assertFalse($other->isFillable('google_subject'));
        $this->assertSame('ascii_bin', DB::selectOne("SHOW FULL COLUMNS FROM users WHERE Field = 'google_subject'")->Collation);
        $this->assertSame(32, count(DB::select('SHOW TABLES')));
        $this->expectException(UniqueConstraintViolationException::class);
        $other->google_subject = $owner->google_subject;
        $other->save();
    }

    public function test_provider_failure_does_not_expose_tokens_and_clears_pending_state(): void
    {
        $state = $this->begin();
        $this->http->append(new Response(500, [], 'fake-sensitive-provider-response'));
        $response = $this->get(route('google.callback', ['state' => $state, 'code' => 'fake-sensitive-code']));
        $response->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertStringNotContainsString('fake-sensitive', session('errors')->first('google'));
        $this->assertNull(session('google_flow'));
        $this->assertNull(session('state'));
        $this->assertGuest();
    }

    public function test_account_locked_during_google_link_is_revoked(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('google.link'), ['current_password' => 'Password123']);
        $state = session('state');
        User::whereKey($user->id)->update(['status' => 'locked']);
        $this->get(route('google.callback', ['state' => $state, 'code' => 'fake']))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_subject);
    }

    public function test_rollback_refuses_to_remove_existing_google_identities(): void
    {
        $this->user(UserRole::Candidate, 'preserved-subject');
        $migration = require database_path('migrations/2026_11_16_210000_add_google_subject_to_users_table.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove Google identities');
        $migration->down();
    }

    public function test_google_login_keeps_password_set_through_email_recovery(): void
    {
        $user = $this->user(UserRole::Candidate, 'recovered-google');
        $token = Password::broker('users')->createToken($user);
        $this->post(route('password.update'), [
            'email' => $user->email, 'token' => $token,
            'password' => 'RecoveredPassword123', 'password_confirmation' => 'RecoveredPassword123',
        ])->assertRedirect(route('login'));
        $passwordHash = $user->fresh()->password;
        $this->googleCallback($this->begin(), 'recovered-google')->assertRedirect(route('candidate.profile'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('RecoveredPassword123', $passwordHash));
    }
}
