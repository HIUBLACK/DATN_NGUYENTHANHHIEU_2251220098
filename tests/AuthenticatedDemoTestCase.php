<?php

namespace Tests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class AuthenticatedDemoTestCase extends TestCase
{
    protected UserRole $demoRole = UserRole::Candidate;

    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            $this->markTestSkipped('Run php tests/Database/models.php --auth for authenticated demo regression.');
        }
        DB::beginTransaction();
        $this->loginDemoUser($this->demoRole);
    }

    protected function loginDemoUser(UserRole $role): void
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Demo Test', 'email' => uniqid().'@test.invalid',
            'password' => 'Password123', 'role' => $role, 'status' => UserStatus::Active,
        ])->save();
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        if ($this->app && DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }
}
