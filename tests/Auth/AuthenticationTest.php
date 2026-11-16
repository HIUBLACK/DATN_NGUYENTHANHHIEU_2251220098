<?php

namespace Tests\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_real_console_reads_input_without_autoload_failure_on_windows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows code page regression.');
        }

        $process = new Process([PHP_BINARY, 'artisan', 'admin:create'], base_path());
        $process->setInput("Console test\ninvalid-email\n");
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Email không hợp lệ.', $process->getOutput());
        $this->assertStringNotContainsString('Fatal error', $process->getOutput().$process->getErrorOutput());
        $this->assertSame(0, User::count());

        $aborted = new Process([PHP_BINARY, 'artisan', 'admin:create'], base_path());
        $aborted->setInput('');
        $aborted->run();
        $this->assertNotSame(0, $aborted->getExitCode());
        $this->assertStringNotContainsString('Failed to open stream', $aborted->getOutput().$aborted->getErrorOutput());
        $this->assertStringNotContainsString('Fatal error', $aborted->getOutput().$aborted->getErrorOutput());
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use php tests/Database/models.php --auth with an isolated database.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function account(UserRole $role = UserRole::Candidate, UserStatus $status = UserStatus::Active): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Người dùng kiểm thử', 'email' => uniqid().'@test.invalid',
            'password' => 'Password123', 'role' => $role, 'status' => $status,
        ])->save();

        return $user;
    }

    private function registration(string $role): array
    {
        return ['name' => 'Nguyễn Văn An', 'email' => 'register@test.invalid', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'role' => $role];
    }

    public function test_candidate_registration_creates_only_user_and_profile_with_safe_defaults(): void
    {
        $this->post(route('auth.register'), $this->registration('candidate') + ['status' => 'locked', 'id' => 99999])
            ->assertRedirect(route('candidate.profile'))->assertSessionHasNoErrors();
        $user = User::where('email', 'register@test.invalid')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Candidate, $user->role);
        $this->assertTrue($user->has_local_password);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotSame(99999, $user->id);
        $this->assertNotNull($user->candidateProfile);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertNull($user->remember_token);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_recruiter_registration_does_not_create_company(): void
    {
        $this->post(route('auth.register'), $this->registration('recruiter'))->assertRedirect(route('recruiter.dashboard'));
        $user = User::where('email', 'register@test.invalid')->firstOrFail();
        $this->assertSame(UserRole::Recruiter, $user->role);
        $this->assertTrue($user->has_local_password);
        $this->assertNull($user->company);
        $this->assertNull($user->candidateProfile);
        $this->assertSame(0, Company::count());
    }

    public function test_public_admin_registration_and_duplicate_email_are_rejected(): void
    {
        $this->post(route('auth.register'), $this->registration('admin'))->assertSessionHasErrors('role');
        $this->assertSame(0, User::count());
        $user = $this->account();
        $input = $this->registration('candidate');
        $input['email'] = strtoupper($user->email);
        $this->post(route('auth.register'), $input)->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_login_rotates_session_ignores_client_role_and_never_remembers(): void
    {
        $user = $this->account();
        $this->withSession(['candidate_preview' => ['secret' => 'previous account']]);
        $sessionId = session()->getId();
        $response = $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'Password123', 'role' => 'admin', 'remember' => true]);
        $response->assertRedirect(route('candidate.profile'))->assertSessionMissing('candidate_preview');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue($user->fresh()->has_local_password);
        $this->assertNull($user->fresh()->remember_token);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString('remember_', $cookie->getName());
        }
    }

    public function test_wrong_password_and_locked_account_return_same_error(): void
    {
        $user = $this->account();
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $message = session('errors')->first('email');
        $user->status = UserStatus::Locked;
        $user->save();
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->assertSame($message, session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_rate_limit_blocks_repeated_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('auth.login'), ['email' => 'missing@test.invalid', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson(route('auth.login'), ['email' => 'missing@test.invalid', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_locked_account_is_revoked_on_next_request(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get(route('candidate.profile'))->assertOk();
        User::whereKey($user->id)->update(['status' => 'locked']);
        $this->get(route('candidate.profile'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('candidate.resumes'))->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_session_and_demo_data(): void
    {
        $user = $this->account();
        $this->actingAs($user)->withSession(['candidate_preview' => ['private' => true]]);
        $sessionId = session()->getId();
        $token = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('candidate_preview');
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get(route('candidate.profile'))->assertRedirect(route('login'));
    }

    public function test_logout_requires_post_and_auth_forms_never_flash_passwords(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get(route('logout'))->assertStatus(405);
        $this->post(route('password.change'), ['current_password' => 'secret-current', 'password' => 'weak'])
            ->assertSessionHasErrors();
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_role_matrix_blocks_every_cross_role_private_area(): void
    {
        $routes = ['candidate.profile', 'recruiter.dashboard', 'admin.dashboard'];
        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        foreach ([UserRole::Candidate, UserRole::Recruiter, UserRole::Admin] as $index => $role) {
            $user = $this->account($role);
            $this->actingAs($user);
            foreach ($routes as $routeIndex => $route) {
                $response = $this->get(route($route).'?role=admin&user_id='.$user->id);
                $routeIndex === $index ? $response->assertOk() : $response->assertForbidden();
            }
            $this->post(route('logout'));
        }
    }

    public function test_change_password_checks_current_password_and_revokes_all_sessions(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $input = ['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'];
        $this->post(route('password.change'), $input)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
        DB::table('sessions')->insert(['id' => 'other-device-test', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $input['current_password'] = 'Password123';
        $this->post(route('password.change'), $input)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertTrue($user->fresh()->has_local_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_csrf_is_enforced_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('auth.register'), $this->registration('candidate'))->assertStatus(419);
        $this->assertSame(0, User::count());
        $this->withSession(['_token' => 'csrf-test-token'])
            ->post(route('auth.register'), $this->registration('candidate') + ['_token' => 'csrf-test-token'])
            ->assertRedirect(route('candidate.profile'));
    }

    public function test_demo_auth_cannot_bypass_real_login_and_reset_email_is_not_simulated(): void
    {
        $this->post(route('demo.enter'))->assertRedirect(route('login'));
        $this->post('/demo/tai-khoan/login', ['email' => 'fake@test.invalid', 'password' => 'Password123'])->assertNotFound();
        $this->get(route('forgot'))->assertRedirect(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Chưa có email khôi phục nào được gửi');
        $this->assertGuest();
        $this->get(route('register'))->assertSee('name="role"', false)->assertDontSee('value="admin"', false);
    }

    public function test_login_and_registration_are_separate_pages_with_canonical_routes(): void
    {
        $this->assertSame('/login', route('login', [], false));
        $this->assertSame('/register', route('register', [], false));
        $this->get('/login')->assertOk()->assertViewIs('Candidate.ca_pages.login')
            ->assertSee('action="'.route('auth.login').'"', false)
            ->assertDontSee('action="'.route('auth.register').'"', false)
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="password_confirmation"', false)
            ->assertDontSee('href="'.route('forgot').'"', false)
            ->assertSee('href="'.route('register').'"', false);
        $this->get('/register')->assertOk()->assertViewIs('Candidate.ca_pages.register')
            ->assertSee('action="'.route('auth.register').'"', false)
            ->assertDontSee('action="'.route('auth.login').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('value="candidate"', false)
            ->assertSee('value="recruiter"', false)
            ->assertDontSee('value="admin"', false)
            ->assertSee('href="'.route('login').'"', false);
        $this->get('/dang-nhap')->assertRedirect('/login');
        $this->get('/dang-ky')->assertRedirect('/register');
        $this->get('/admin/login')->assertNotFound();
    }

    public function test_shared_login_redirects_all_roles_using_database_role(): void
    {
        $destinations = [
            'candidate' => 'candidate.profile',
            'recruiter' => 'recruiter.dashboard',
            'admin' => 'admin.dashboard',
        ];
        foreach ($destinations as $role => $destination) {
            $user = $this->account(UserRole::from($role));
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'Password123',
                'role' => $role === 'admin' ? 'candidate' : 'admin',
            ])->assertRedirect(route($destination));
            $this->assertAuthenticatedAs($user);
            $this->get(route($destination))->assertOk();
            $this->post(route('logout'))->assertRedirect('/login');
        }
    }

    public function test_admin_command_creates_hashed_admin_without_related_business_records(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Họ tên', 'Quản trị viên')
            ->expectsQuestion('Email', ' ADMIN@Test.Invalid ')
            ->expectsQuestion('Mật khẩu (ít nhất 8 ký tự, có chữ và số)', 'AdminPassword123')
            ->expectsQuestion('Xác nhận mật khẩu', 'AdminPassword123')
            ->expectsOutput('Đã tạo tài khoản Admin. Đăng nhập tại /login.')
            ->assertSuccessful();
        $admin = User::where('email', 'admin@test.invalid')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->has_local_password);
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertTrue(Hash::check('AdminPassword123', $admin->password));
        $this->assertNull($admin->remember_token);
        $this->assertNull($admin->last_login_at);
        $this->assertSame(0, CandidateProfile::count());
        $this->assertSame(0, Company::count());
        $this->post('/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_command_rejects_duplicate_email_without_promoting_existing_user(): void
    {
        $user = $this->account();
        $this->artisan('admin:create')
            ->expectsQuestion('Họ tên', 'Quản trị viên')
            ->expectsQuestion('Email', strtoupper($user->email))
            ->expectsOutput('Email đã được sử dụng. Không thay đổi tài khoản hiện có.')
            ->assertFailed();
        $this->assertSame(1, User::count());
        $this->assertSame(UserRole::Candidate, $user->fresh()->role);
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }

    public function test_admin_command_rejects_weak_or_unconfirmed_passwords(): void
    {
        foreach ([['weak', 'weak'], ['AdminPassword123', 'different']] as [$password, $confirmation]) {
            $this->artisan('admin:create')
                ->expectsQuestion('Họ tên', 'Quản trị viên')
                ->expectsQuestion('Email', 'admin@test.invalid')
                ->expectsQuestion('Mật khẩu (ít nhất 8 ký tự, có chữ và số)', $password)
                ->expectsQuestion('Xác nhận mật khẩu', $confirmation)
                ->assertFailed();
            $this->assertSame(0, User::count());
        }
    }

    public function test_admin_command_requires_interactive_input(): void
    {
        $this->artisan('admin:create', ['--no-interaction' => true])
            ->expectsOutput('Vui lòng chạy tương tác để nhập mật khẩu an toàn.')
            ->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_database_session_is_destroyed_on_logout_and_cannot_be_replayed(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->account();
        $this->get(route('login'))->assertOk();
        $guestSession = session()->getId();
        $this->withCookie(config('session.cookie'), $guestSession);
        $this->post(route('auth.login'), ['email' => $user->email, 'password' => 'Password123'])->assertRedirect();
        $authenticatedSession = session()->getId();
        $this->assertNotSame($guestSession, $authenticatedSession);
        $this->assertFalse(DB::table('sessions')->where('id', $guestSession)->exists());
        $this->assertSame($user->id, (int) DB::table('sessions')->where('id', $authenticatedSession)->value('user_id'));
        $this->withCookie(config('session.cookie'), $authenticatedSession);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertFalse(DB::table('sessions')->where('id', $authenticatedSession)->exists());
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->withCookie(config('session.cookie'), $authenticatedSession)->get(route('candidate.profile'))->assertRedirect(route('login'));
    }

    public function test_account_switch_cannot_read_previous_demo_pdf(): void
    {
        Storage::fake('local');
        $first = $this->account();
        $second = $this->account();
        $this->actingAs($first)->post(route('candidate.upload'), [
            'pdf' => UploadedFile::fake()->createWithContent('private.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertSessionHasNoErrors();
        $resumeId = session('candidate_preview.resumes.0.id');
        $this->get(route('candidate.pdf', $resumeId))->assertOk();
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('auth.login'), ['email' => $second->email, 'password' => 'Password123'])->assertRedirect();
        $this->get(route('candidate.pdf', $resumeId))->assertNotFound();
    }

    public function test_demo_company_owner_cannot_be_overridden_by_client_id(): void
    {
        $first = $this->account(UserRole::Recruiter);
        $second = $this->account(UserRole::Recruiter);
        $this->actingAs($first)->get(route('recruiter.company-edit'))->assertOk();
        $company = session('company_profile_preview');
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('auth.login'), ['email' => $second->email, 'password' => 'Password123'])->assertRedirect();
        $this->put(route('recruiter.company-update', $company['id']), [
            'recruiter_id' => $first->id, 'user_id' => $first->id, 'name' => 'Forged',
        ])->assertForbidden();
    }

    public function test_registration_rolls_back_user_if_profile_creation_fails(): void
    {
        CandidateProfile::creating(function () {
            throw new \RuntimeException('Test profile creation failure');
        });
        try {
            $this->post(route('auth.register'), $this->registration('candidate'))->assertStatus(500);
            $this->assertSame(0, User::count());
            $this->assertGuest();
        } finally {
            CandidateProfile::flushEventListeners();
        }
    }
}
