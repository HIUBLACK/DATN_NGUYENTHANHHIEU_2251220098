<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Job;
use App\Models\Resume;
use App\Models\User;
use App\Notifications\RecruiterApplicationNotice;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use App\Services\RecruiterDashboardService;
use App\Services\RecruiterJobService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class RecruiterDashboardTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;
    use PdfFixtures;

    private User $candidate;

    private User $owner;

    private Job $job;

    private Resume $resume;

    private array $jobInput;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->candidate = auth()->user();
        $this->owner = new User;
        $this->owner->forceFill([
            'name' => 'Dashboard owner', 'email' => Str::uuid().'@test.invalid',
            'password' => 'Password123', 'role' => 'recruiter', 'status' => 'active',
        ])->save();
        $this->companyFixture($this->owner->id);
        $this->jobInput = $this->completeInput();
        $this->job = app(RecruiterJobService::class)->create($this->owner, $this->jobInput);
        $this->publishFixture($this->job);
        $this->resume = app(CandidateResumeService::class)->upload($this->candidate, $this->pdf());
    }

    private function applicationInput(int $attempt = 1): array
    {
        return ['resume' => $this->resume->public_id, 'attempt_no' => $attempt, 'job_version_id' => $this->job->published_version_id, 'confirmation' => 1];
    }

    private function apply(int $attempt = 1): Application
    {
        return app(CandidateApplicationService::class)->apply($this->candidate, $this->job->id, $this->applicationInput($attempt));
    }

    public function test_dashboard_counts_jobs_not_versions_and_each_application_attempt_once(): void
    {
        $first = $this->apply();
        app(CandidateApplicationService::class)->withdraw($this->candidate, $first->public_id);
        $this->apply(2);
        $jobs = app(RecruiterJobService::class);
        $version = $jobs->latest($this->job);
        $jobs->update($this->owner, $this->job->id, ['title' => 'Pending version'] + $this->jobInput + ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
        $version = $jobs->latest($this->job);
        $jobs->submit($this->owner, $this->job->id, ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
        $counts = app(RecruiterDashboardService::class)->data($this->owner)['dashboardCounts'];
        $this->assertSame(1, $counts['jobTotal']);
        $this->assertSame(1, $counts['jobs']['open']);
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(2, $counts['applicationTotal']);
        $this->assertSame(['applied' => 1, 'withdrawn' => 1], $counts['applications']);
        DB::table('jobs')->where('id', $this->job->id)->update(['expires_at' => now()->subDay()]);
        $counts = app(RecruiterDashboardService::class)->data($this->owner)['dashboardCounts'];
        $this->assertSame(0, $counts['jobs']['open']);
        $this->assertSame(1, $counts['jobs']['expired']);
    }

    public function test_statistics_and_notifications_are_private_to_the_recruiter(): void
    {
        $this->apply();
        $this->flushSession();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->get(route('recruiter.dashboard'))->assertOk()->assertViewHas('dashboardCounts', fn ($counts) => $counts['jobTotal'] === 0 && $counts['applicationTotal'] === 0);
        $this->get(route('recruiter.notifications'))->assertOk()->assertViewHas('notifications', fn ($items) => $items->total() === 0);
        $this->get(route('recruiter.dashboard'))->assertDontSee($this->jobInput['title']);
        $this->flushSession();
        $this->actingAs($this->candidate)->get(route('recruiter.dashboard'))->assertForbidden();
    }

    public function test_apply_withdraw_notices_are_atomic_and_duplicate_requests_do_not_repeat(): void
    {
        $this->post(route('candidate.apply', $this->job->id), $this->applicationInput())->assertSessionHasNoErrors();
        $application = Application::firstOrFail();
        $this->post(route('candidate.apply', $this->job->id), $this->applicationInput())->assertSessionHasErrors('application');
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->post(route('candidate.withdraw', $application->public_id))->assertSessionHasNoErrors();
        $this->post(route('candidate.withdraw', $application->public_id))->assertForbidden();
        $this->assertSame(2, $this->owner->notifications()->count());
        $notice = $this->owner->notifications()->where('data->status', 'withdrawn')->firstOrFail();
        $this->assertSame($application->public_id, $notice->data['application_id']);
        $this->assertSame(1, $notice->data['attempt']);
        $this->assertSame(['database'], (new RecruiterApplicationNotice('id', 'title', 'name', 1, 'applied'))->via($this->owner));
        $this->assertArrayNotHasKey('resume_snapshot_path', $notice->data);
        DB::beginTransaction();
        $this->apply(2);
        $this->assertSame(3, $this->owner->notifications()->count());
        DB::rollBack();
        $this->assertSame(2, $this->owner->notifications()->count());
        $this->assertSame(1, Application::count());
    }

    public function test_read_one_all_and_filters_cannot_modify_another_users_notifications(): void
    {
        $application = $this->apply();
        app(CandidateApplicationService::class)->withdraw($this->candidate, $application->public_id);
        $id = $this->owner->notifications()->firstOrFail()->id;
        $candidateNotice = $this->candidate->notifications()->firstOrFail();
        $this->flushSession();
        $this->actingAs($this->owner);
        $this->get(route('recruiter.notifications', ['filter' => 'withdrawn']))->assertOk()->assertViewHas('notifications', fn ($items) => $items->total() === 1);
        $this->post(route('recruiter.notification-read', $candidateNotice->id))->assertNotFound();
        $this->post(route('recruiter.notification-read', $id))->assertSessionHasNoErrors();
        $this->post(route('recruiter.notification-read', $id))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->owner->unreadNotifications()->count());
        $this->get(route('recruiter.notifications', ['filter' => 'unread']))->assertOk()->assertSee('1 thông báo chưa đọc');
        $this->post(route('recruiter.notification-read', 'all'))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->owner->unreadNotifications()->count());
        $this->assertNull($candidateNotice->fresh()->read_at);
        $this->get(route('recruiter.application-detail', ['id' => $application->public_id]))->assertOk();
    }

    public function test_empty_dashboard_and_existing_application_rules_remain_usable(): void
    {
        $this->flushSession();
        $this->actingAs($this->owner);
        $this->get(route('recruiter.dashboard'))->assertOk()->assertSee('Chưa có hồ sơ ứng tuyển.')
            ->assertSee('recruiter-job-chart')->assertSee('card-stat')->assertDontSee('phiên demo');
        $this->get(route('recruiter.notifications'))->assertOk()->assertSee('Không có thông báo trong mục này.');
        DB::table('jobs')->where('id', $this->job->id)->update(['status' => 'paused']);
        $this->flushSession();
        $this->actingAs($this->candidate);
        $this->post(route('candidate.apply', $this->job->id), $this->applicationInput())->assertSessionHasErrors('application');
        $this->assertSame(0, $this->owner->notifications()->count());
        $this->assertSame(0, Application::count());
    }
}
