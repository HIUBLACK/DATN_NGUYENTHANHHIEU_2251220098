<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class AdminManagementTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;
    use PdfFixtures;

    protected UserRole $demoRole = UserRole::Admin;

    private function user(string $role = 'recruiter'): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Managed '.$role, 'email' => Str::uuid().'@test.invalid', 'password' => 'Password123', 'role' => $role, 'status' => 'active'])->save();

        return $user;
    }

    private function input($record, string $status): array
    {
        return ['status' => $status, 'expected_status' => $record->status->value, 'expected_log_id' => (int) $record->activityLogs()->max('id'), 'updated_at' => $record->getRawOriginal('updated_at'), 'reason' => 'Administrative review'];
    }

    private function fixture(): array
    {
        Storage::fake('local');
        $owner = $this->user();
        $company = $this->companyFixture($owner->id);
        $candidate = $this->user('candidate');
        $input = $this->completeInput();
        $job = app(RecruiterJobService::class)->create($owner, $input);
        $this->publishFixture($job);
        $resume = app(CandidateResumeService::class)->upload($candidate, $this->pdf());
        $applicationInput = ['resume' => $resume->public_id, 'attempt_no' => 1, 'job_version_id' => $job->published_version_id, 'confirmation' => 1];
        $application = app(CandidateApplicationService::class)->apply($candidate, $job->id, $applicationInput);

        return compact('owner', 'company', 'candidate', 'job', 'application', 'applicationInput', 'input');
    }

    public function test_dashboard_counts_versions_attempts_and_empty_state(): void
    {
        $empty = app(AdminDashboardService::class)->data([]);
        $this->assertSame(0, $empty['systemMetrics'][2][1]);
        $this->assertSame(0, $empty['applicationMetrics'][0][1]);
        $fixture = $this->fixture();
        app(CandidateApplicationService::class)->withdraw($fixture['candidate'], $fixture['application']->public_id);
        app(CandidateApplicationService::class)->apply($fixture['candidate'], $fixture['job']->id, array_replace($fixture['applicationInput'], ['attempt_no' => 2]));
        $jobs = app(RecruiterJobService::class);
        $version = $jobs->latest($fixture['job']);
        $jobs->update($fixture['owner'], $fixture['job']->id, ['title' => 'Pending edit'] + $fixture['input'] + ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
        $version = $jobs->latest($fixture['job']);
        $jobs->submit($fixture['owner'], $fixture['job']->id, ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
        $data = app(AdminDashboardService::class)->data([]);
        $this->assertSame(1, $data['userMetrics'][0][1]);
        $this->assertSame(1, $data['userMetrics'][1][1]);
        $this->assertSame(1, $data['systemMetrics'][2][1]);
        $this->assertSame(1, $data['systemMetrics'][3][1]);
        $this->assertSame(1, $data['systemMetrics'][4][1]);
        $this->assertSame(2, $data['applicationMetrics'][0][1]);
        $this->assertSame(2, array_sum(array_column($data['trend'], 'applications')));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('admin-real-trend')->assertDontSee('admin-assets/js/admin.js');
        $this->getJson(route('admin.dashboard', ['start' => '2026-01-01', 'end' => '2028-01-01']))->assertUnprocessable();
    }

    public function test_lists_filter_paginate_and_show_real_company_profile(): void
    {
        $fixture = $this->fixture();
        for ($index = 0; $index < 15; $index++) {
            $this->user('candidate');
        }
        $this->get(route('admin.users.index', ['role' => 'candidate']))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 16 && $records->count() === 15);
        $this->get(route('admin.users.index', ['role' => 'candidate', 'page' => 2]))->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1);
        $this->get(route('admin.users.index', ['q' => $fixture['owner']->email]))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('admin.users.show', ['id' => $fixture['owner']->id]))->assertOk()->assertSee($fixture['company']->name);
        $this->get(route('admin.companies.index', ['q' => 'does-not-exist']))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 0);
        $this->get(route('admin.companies.show', ['id' => $fixture['company']->id]))->assertOk()->assertSee($fixture['company']->email)->assertDontSee('admin-assets/js/admin.js');
    }

    public function test_user_lock_unlock_audit_repeated_and_stale_requests(): void
    {
        $admin = auth()->user();
        $user = $this->user('candidate');
        $url = route('admin.management.status', ['users', $user->id]);
        $input = $this->input($user, 'locked');
        $this->patch($url, $input + ['role' => 'admin', 'name' => 'Injected'])->assertRedirect();
        $this->assertSame('locked', $user->fresh()->status->value);
        $this->assertSame('candidate', $user->fresh()->role->value);
        $this->assertSame('Managed candidate', $user->fresh()->name);
        $this->patch($url, $input)->assertSessionHasErrors('status');
        $this->assertSame(1, $user->activityLogs()->count());
        $log = $user->activityLogs()->first();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(['status' => 'active'], $log->old_values);
        $this->patch($url, $this->input($user->fresh(), 'active'))->assertRedirect();
        $this->patch($url, $input)->assertSessionHasErrors('status');
        $this->assertSame(2, $user->activityLogs()->count());
        $this->patch(route('admin.management.status', ['users', $admin->id]), $this->input($admin, 'locked'))->assertForbidden();
        $this->patch($url, array_replace($this->input($user->fresh(), 'locked'), ['reason' => '']))->assertSessionHasErrors('reason');
    }

    public function test_company_lock_preserves_jobs_applications_and_public_pointer(): void
    {
        $fixture = $this->fixture();
        $company = $fixture['company'];
        $job = $fixture['job'];
        $url = route('admin.management.status', ['companies', $company->id]);
        $this->patch($url, $this->input($company, 'locked'))->assertRedirect();
        $this->assertSame(0, app(PublicJobService::class)->query()->count());
        $this->assertSame('open', $job->fresh()->status->value);
        $this->assertSame($job->published_version_id, $job->fresh()->published_version_id);
        $this->assertSame(1, Application::count());
        $this->assertFalse(Gate::forUser($fixture['owner'])->allows('changeStatus', $fixture['application']->fresh()));
        $this->patch($url, $this->input($company->fresh(), 'active'))->assertRedirect();
        $this->assertSame(1, app(PublicJobService::class)->query()->count());
        $this->assertSame(2, $company->activityLogs()->count());
        $this->assertSame($job->published_version_id, $job->fresh()->published_version_id);
    }

    public function test_application_supervision_respects_existing_cv_and_notes_permissions(): void
    {
        $fixture = $this->fixture();
        $application = $fixture['application'];
        $note = new ApplicationNote;
        $note->forceFill(['application_id' => $application->id, 'recruiter_id' => $fixture['owner']->id, 'content' => 'Private recruiter assessment'])->save();
        $this->get(route('admin.applications.index', ['candidate' => $fixture['candidate']->email, 'company' => $fixture['company']->name, 'job' => $application->jobVersion->title, 'recruiter' => $fixture['owner']->email, 'status' => 'applied']))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('admin.applications.show', ['id' => $application->public_id]))
            ->assertOk()->assertSee($fixture['candidate']->email)
            ->assertDontSee('Private recruiter assessment')->assertDontSee($application->resume_snapshot_path);
        $this->assertFalse(Gate::allows('viewSnapshot', $application));
        $this->assertFalse(Gate::allows('viewNotes', $application));
        $this->get(route('candidate.snapshot', $application->public_id))->assertForbidden();
        $this->get(route('recruiter.application-pdf', $application->public_id))->assertForbidden();
        app(CandidateApplicationService::class)->withdraw($fixture['candidate'], $application->public_id);
        $this->get(route('admin.applications.show', ['id' => $application->public_id]))
            ->assertOk()->assertViewHas('history', fn ($history) => $history->count() === 2);
    }

    public function test_non_admin_and_locked_admin_cannot_manage_system(): void
    {
        $admin = auth()->user();
        $target = $this->user();
        $url = route('admin.management.status', ['users', $target->id]);
        foreach (['candidate', 'recruiter'] as $role) {
            $this->flushSession();
            $this->actingAs($this->user($role));
            $this->get(route('admin.users.index'))->assertForbidden();
            $this->patch($url, $this->input($target, 'locked'))->assertForbidden();
        }
        $this->flushSession();
        auth()->logout();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $admin->status = 'locked';
        $admin->save();
        $this->actingAs($admin);
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertSame('active', $target->fresh()->status->value);
        $this->assertSame(0, ActivityLog::count());
    }
}
