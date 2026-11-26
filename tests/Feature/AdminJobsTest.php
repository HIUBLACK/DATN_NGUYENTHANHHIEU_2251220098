<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Job;
use App\Models\JobVersion;
use App\Models\User;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use App\Services\JobModerationService;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class AdminJobsTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;
    use PdfFixtures;

    protected UserRole $demoRole = UserRole::Admin;

    private User $admin;

    private User $owner;

    private Job $job;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = auth()->user();
        $this->owner = $this->user('recruiter');
        $this->companyFixture($this->owner->id);
        $this->input = $this->completeInput();
        $this->job = app(RecruiterJobService::class)->create($this->owner, $this->input);
        $this->publishFixture($this->job);
    }

    private function user(string $role): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Admin Jobs '.$role, 'email' => Str::uuid().'@test.invalid', 'password' => 'Password123', 'role' => $role, 'status' => 'active'])->save();

        return $user;
    }

    private function token(Job $job, string $status): array
    {
        $job->refresh();

        return ['status' => $status, 'expected_status' => $job->status->value, 'updated_at' => $job->getRawOriginal('updated_at'), 'expected_log_id' => (int) $job->activityLogs()->max('id'), 'reason' => 'Review violation'];
    }

    private function change(Job $job, string $status)
    {
        return $this->patch(route('admin.jobs.status', $job->id), $this->token($job, $status));
    }

    private function pending(): JobVersion
    {
        $service = app(RecruiterJobService::class);
        $version = $service->latest($this->job);
        $service->update($this->owner, $this->job->id, ['title' => 'Pending replacement'] + $this->input + ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
        $version = $service->latest($this->job);
        $service->submit($this->owner, $this->job->id, ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);

        return $version->fresh();
    }

    public function test_list_search_filters_and_pagination_count_jobs_not_versions(): void
    {
        $this->pending();
        $this->get(route('admin.jobs.index', ['q' => 'Pending replacement', 'status' => 'open', 'review' => 'pending']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1)->assertDontSee('admin-assets/js/admin.js');
        $this->get(route('admin.jobs.index', ['q' => $this->job->company->name]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.jobs.index', ['review' => 'approved']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        for ($index = 0; $index < 15; $index++) {
            app(RecruiterJobService::class)->create($this->owner, ['title' => 'Draft '.$index] + $this->input);
        }
        $this->get(route('admin.jobs.index'))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $this->get(route('admin.jobs.index', ['page' => 2]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->count() === 1);
    }

    public function test_details_show_exact_versions_and_moderation_history(): void
    {
        $publishedId = $this->job->published_version_id;
        $version = $this->pending();
        $this->get(route('admin.jobs.show', ['id' => $this->job->id]))->assertOk()
            ->assertViewHas('published', fn ($item) => $item->id === $publishedId)
            ->assertViewHas('selected', fn ($item) => $item->id === $version->id)
            ->assertSee($this->job->publishedVersion->positionCatalog->name);
        app(JobModerationService::class)->decide($this->admin, $version->id, 'reject', [
            'updated_at' => $version->getRawOriginal('updated_at'), 'job_updated_at' => $this->job->fresh()->getRawOriginal('updated_at'), 'reason' => 'Missing deployment detail',
        ]);
        $this->get(route('admin.jobs.history', ['id' => $this->job->id]))->assertOk()->assertSee('Missing deployment detail')->assertViewHas('moderation', fn ($items) => $items->total() === 1);
        $this->get(route('admin.jobs.show', ['id' => $this->job->id, 'version' => $publishedId]))->assertOk()->assertViewHas('selected', fn ($item) => $item->id === $publishedId);
        $other = app(RecruiterJobService::class)->create($this->owner, $this->input);
        $this->get(route('admin.jobs.show', ['id' => $this->job->id, 'version' => $other->latestVersion->id]))->assertNotFound();
    }

    public function test_block_requires_reason_and_prevents_public_view_and_applications(): void
    {
        $input = $this->token($this->job, 'blocked');
        $this->patch(route('admin.jobs.status', $this->job->id), array_replace($input, ['reason' => '']))->assertSessionHasErrors('reason');
        $this->change($this->job, 'blocked')->assertRedirect()->assertSessionHasNoErrors();
        $job = $this->job->fresh();
        $this->assertSame('blocked', $job->status->value);
        $this->assertSame('open', $job->status_before_block->value);
        $log = $job->activityLogs()->latest('id')->first();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('job.block', $log->action);
        $this->assertSame('Review violation', $log->reason);
        $this->assertSame(['status' => 'open', 'status_before_block' => null], $log->old_values);
        $this->assertSame(0, app(PublicJobService::class)->query()->count());
        $this->assertFalse(Gate::forUser($this->owner)->allows('operate', $job));
        Storage::fake('local');
        $candidate = $this->user('candidate');
        $resume = app(CandidateResumeService::class)->upload($candidate, $this->pdf());
        $this->flushSession();
        $this->actingAs($candidate);
        $this->get(route('jobs.show', $job->id))->assertNotFound();
        $this->post(route('candidate.apply', $job->id), ['resume' => $resume->public_id, 'attempt_no' => 1, 'job_version_id' => $job->published_version_id, 'confirmation' => 1])->assertSessionHasErrors();
        $this->assertSame(0, $job->applications()->count());
    }

    public function test_unblock_restores_safely_without_publishing_pending_or_rejected_versions(): void
    {
        $publishedId = $this->job->published_version_id;
        $pending = $this->pending();
        $this->change($this->job, 'blocked')->assertSessionHasNoErrors();
        $this->change($this->job, 'restore')->assertSessionHasNoErrors();
        $this->assertSame('open', $this->job->fresh()->status->value);
        $this->assertSame('pending', $pending->fresh()->review_status->value);
        $this->assertSame($publishedId, $this->job->fresh()->published_version_id);
        app(JobModerationService::class)->decide($this->admin, $pending->id, 'reject', [
            'updated_at' => $pending->getRawOriginal('updated_at'), 'job_updated_at' => $this->job->fresh()->getRawOriginal('updated_at'), 'reason' => 'Rejected edit',
        ]);
        $moderationBefore = $this->job->moderationLogs()->get()->toJson();
        $this->change($this->job, 'blocked')->assertSessionHasNoErrors();
        $this->change($this->job, 'restore')->assertSessionHasNoErrors();
        $this->assertSame('rejected', $pending->fresh()->review_status->value);
        $this->assertSame($moderationBefore, $this->job->moderationLogs()->get()->toJson());
        foreach (['expired', 'company', 'owner'] as $condition) {
            $this->job->refresh();
            $this->job->status = 'open';
            $this->job->expires_at = now()->addDay();
            $this->job->save();
            $this->change($this->job, 'blocked')->assertSessionHasNoErrors();
            if ($condition === 'expired') {
                $this->job->refresh();
                $this->job->expires_at = now()->subMinute();
                $this->job->save();
            } else {
                $record = $condition === 'company' ? $this->job->company : $this->owner;
                $record->status = 'locked';
                $record->save();
            }
            $this->change($this->job, 'restore')->assertSessionHasNoErrors();
            $this->assertSame('paused', $this->job->fresh()->status->value);
            $this->assertSame($publishedId, $this->job->fresh()->published_version_id);
            if (isset($record)) {
                $record->status = 'active';
                $record->save();
            }
        }
        foreach (['draft', 'paused', 'closed'] as $status) {
            $job = app(RecruiterJobService::class)->create($this->owner, $this->input);
            if ($status !== 'draft') {
                $this->publishFixture($job);
                $job->status = $status;
                $job->save();
            }
            $this->change($job, 'blocked')->assertSessionHasNoErrors();
            $this->change($job, 'restore')->assertSessionHasNoErrors();
            $this->assertSame($status, $job->fresh()->status->value);
            $this->assertNull($job->fresh()->status_before_block);
        }
    }

    public function test_permissions_replay_and_preservation_of_application_evidence(): void
    {
        Storage::fake('local');
        $candidate = $this->user('candidate');
        $resume = app(CandidateResumeService::class)->upload($candidate, $this->pdf());
        $application = app(CandidateApplicationService::class)->apply($candidate, $this->job->id, ['resume' => $resume->public_id, 'attempt_no' => 1, 'job_version_id' => $this->job->published_version_id, 'confirmation' => 1]);
        $before = $application->fresh()->getRawOriginal();
        $versions = $this->job->versions()->get()->toJson();
        $input = $this->token($this->job, 'blocked');
        $url = route('admin.jobs.status', $this->job->id);
        $this->patch($url, $input + ['published_version_id' => null, 'title' => 'Injected'])->assertSessionHasNoErrors();
        $this->patch($url, $input)->assertSessionHasErrors('job');
        $this->change($this->job, 'restore')->assertSessionHasNoErrors();
        $this->patch($url, $input)->assertSessionHasErrors('job');
        $this->assertSame(2, $this->job->activityLogs()->whereIn('action', ['job.block', 'job.unblock'])->count());
        $this->assertSame($before, $application->fresh()->getRawOriginal());
        $this->assertSame($versions, $this->job->versions()->get()->toJson());
        Storage::disk('local')->assertExists($application->resume_snapshot_path);
        foreach ([$candidate, $this->owner] as $user) {
            $this->flushSession();
            $this->actingAs($user);
            $this->get(route('admin.jobs.index'))->assertForbidden();
            $this->patch($url, $this->token($this->job, 'blocked'))->assertForbidden();
        }
        $this->flushSession();
        auth()->logout();
        $this->get(route('admin.jobs.index'))->assertRedirect(route('login'));
        $this->admin->status = 'locked';
        $this->admin->save();
        $this->actingAs($this->admin);
        $this->patch($url, $this->token($this->job, 'blocked'))->assertRedirect(route('login'));
    }
}
