<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendApplicationReceipt;
use App\Models\Application;
use App\Models\User;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use App\Services\RecruiterApplicationService;
use App\Services\RecruiterJobService;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class RecruiterApplicationsTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;
    use PdfFixtures;

    private User $owner;

    private User $candidate;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        // Run afterCommit at the service boundary, keeping the outer test transaction isolated.
        $connection = DB::connection();
        $manager = new DatabaseTransactionsManager([$connection->getName()]);
        $this->app->instance('db.transactions', $manager);
        $connection->setTransactionManager($manager);
        $manager->begin($connection->getName(), 1);
        Storage::fake('local');
        Queue::fake();
        $this->candidate = auth()->user();
        $this->owner = new User;
        $this->owner->forceFill([
            'name' => 'Recruiter owner', 'email' => Str::uuid().'@test.invalid',
            'password' => 'Password123', 'role' => 'recruiter', 'status' => 'active',
        ])->save();
        $this->companyFixture($this->owner->id);
        $job = app(RecruiterJobService::class)->create($this->owner, $this->completeInput());
        $this->publishFixture($job);
        $resume = app(CandidateResumeService::class)->upload($this->candidate, $this->pdf('Original snapshot'));
        $this->application = app(CandidateApplicationService::class)->apply($this->candidate, $job->id, [
            'resume' => $resume->public_id, 'job_version_id' => $job->published_version_id, 'attempt_no' => 1,
        ]);
        $this->candidate->notifications()->delete();
        Queue::fake();
        $this->flushSession();
        $this->actingAs($this->owner);
    }

    private function statusInput(string $status): array
    {
        $this->application->refresh();

        return [
            'status' => $status, 'expected_status' => $this->application->status->value,
            'updated_at' => $this->application->updated_at->format('Y-m-d H:i:s.u'),
        ];
    }

    private function change(string $status)
    {
        return $this->post(route('recruiter.application-status', $this->application->public_id), $this->statusInput($status));
    }

    public function test_owner_scope_filters_and_permissions(): void
    {
        $this->get(route('recruiter.applications', ['q' => $this->candidate->name, 'status' => 'applied', 'jobId' => $this->application->job_id]))
            ->assertOk()->assertSee($this->candidate->name)->assertSee('table-card-custom')->assertDontSee('hồ sơ minh họa');
        $this->get(route('recruiter.applications', ['status' => 'withdrawn']))->assertOk()->assertSee('Không có hồ sơ phù hợp.');
        $this->get(route('recruiter.application-detail', ['id' => $this->application->public_id]))->assertOk()->assertSee('recruiter-pdf');
        $this->flushSession();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->get(route('recruiter.applications'))->assertOk()->assertViewHas('applications', fn ($applications) => $applications->total() === 0);
        $this->get(route('recruiter.application-detail', ['id' => $this->application->public_id]))->assertNotFound();
        $this->change('reviewing')->assertNotFound();
        $this->post(route('recruiter.application-note', $this->application->public_id), ['content' => 'Private'])->assertNotFound();
        $this->flushSession();
        $this->actingAs($this->candidate)->get(route('recruiter.applications'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->owner);
        DB::table('companies')->where('recruiter_id', $this->owner->id)->update(['status' => 'locked']);
        $this->change('reviewing')->assertNotFound();
        DB::table('companies')->where('recruiter_id', $this->owner->id)->update(['status' => 'active']);
        DB::table('users')->where('id', $this->owner->id)->update(['status' => 'locked']);
        $this->flushSession();
        $this->actingAs($this->owner->fresh());
        $this->postJson(route('recruiter.application-status', $this->application->public_id), $this->statusInput('reviewing'))->assertForbidden();
    }

    public function test_snapshot_is_private_and_unchanged_after_cv_replacement(): void
    {
        $snapshot = Storage::disk('local')->get($this->application->resume_snapshot_path);
        $resume = $this->application->resume;
        app(CandidateResumeService::class)->upload($this->candidate, $this->pdf('New current CV'), $resume->public_id);
        $url = route('recruiter.application-pdf', $this->application->public_id);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent($snapshot);
        $this->get($url.'?download=1')->assertOk()->assertDownload($this->application->resume_snapshot_filename)->assertStreamedContent($snapshot);
        $this->flushSession();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->get($url)->assertNotFound();
        $this->flushSession();
        $this->actingAs($this->candidate)->get($url)->assertForbidden();
    }

    public function test_valid_transitions_record_recruiter_and_preserve_application_identity(): void
    {
        $version = $this->application->job_version_id;
        foreach (['reviewing', 'interview', 'hired'] as $status) {
            $this->change($status)->assertSessionHasNoErrors();
            $this->assertSame($status, $this->application->fresh()->status->value);
            $history = $this->application->statusHistories()->latest('id')->first();
            $this->assertSame($this->owner->id, $history->actor_id);
            $this->assertNotNull($history->created_at);
        }
        $this->assertSame(4, $this->application->statusHistories()->count());
        $this->assertSame($version, $this->application->fresh()->job_version_id);
        $this->assertSame(1, Application::count());
    }

    public function test_invalid_duplicate_stale_withdrawn_and_terminal_requests_are_blocked(): void
    {
        $this->change('hired')->assertSessionHasErrors('status');
        $stale = $this->statusInput('reviewing');
        $this->change('reviewing')->assertSessionHasNoErrors();
        $this->post(route('recruiter.application-status', $this->application->public_id), $stale)->assertSessionHasErrors('status');
        $this->post(route('recruiter.application-status', $this->application->public_id), array_replace($this->statusInput('interview'), ['updated_at' => 'old']))->assertSessionHasErrors('status');
        app(CandidateApplicationService::class)->withdraw($this->candidate, $this->application->public_id);
        $this->post(route('recruiter.application-status', $this->application->public_id), $stale)->assertForbidden();
        $this->get(route('recruiter.applications', ['status' => 'withdrawn']))->assertOk()->assertSee($this->candidate->name);
        foreach (['hired', 'rejected'] as $status) {
            DB::table('applications')->where('id', $this->application->id)->update(['status' => $status, 'withdrawn_at' => null]);
            $this->post(route('recruiter.application-status', $this->application->public_id), $stale)->assertForbidden();
        }
        $this->assertSame(3, $this->application->statusHistories()->count());
    }

    public function test_notes_validate_escape_edit_and_remain_hidden_from_candidate(): void
    {
        $url = route('recruiter.application-note', $this->application->public_id);
        $this->post($url, ['content' => str_repeat('x', 2001)])->assertSessionHasErrors('content');
        $this->post($url, ['content' => '<script>internalSecret()</script>'])->assertSessionHasNoErrors();
        $note = $this->application->notes()->firstOrFail();
        $this->get(route('recruiter.application-detail', ['id' => $this->application->public_id]))
            ->assertOk()->assertSee('&lt;script&gt;internalSecret()', false)->assertDontSee('<script>internalSecret()', false);
        $input = ['content' => 'Internal revised note', 'note_id' => $note->id, 'note_updated_at' => $note->updated_at->format('Y-m-d H:i:s.u')];
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasErrors('content');
        $this->assertSame(1, $this->application->notes()->count());
        $this->assertSame(1, $this->application->statusHistories()->count());
        $this->flushSession();
        $this->actingAs($this->candidate)->get(route('candidate.application-detail', $this->application->public_id))
            ->assertOk()->assertDontSee('Internal revised note')->assertDontSee('internalSecret');
    }

    public function test_notifications_follow_commits_do_not_duplicate_and_mail_failure_keeps_status(): void
    {
        DB::beginTransaction();
        app(RecruiterApplicationService::class)->changeStatus($this->owner, $this->application->public_id, $this->statusInput('reviewing'));
        $this->assertSame(0, $this->candidate->notifications()->count());
        DB::rollBack();
        $this->assertSame('applied', $this->application->fresh()->status->value);
        $this->assertSame(1, $this->application->statusHistories()->count());
        Queue::assertNothingPushed();

        $this->change('reviewing')->assertSessionHasNoErrors();
        $this->assertSame(1, $this->candidate->notifications()->count());
        Queue::assertNothingPushed();
        $input = $this->statusInput('interview');
        $this->post(route('recruiter.application-status', $this->application->public_id), $input)->assertSessionHasNoErrors();
        Queue::assertPushed(SendApplicationReceipt::class, 1);
        $this->post(route('recruiter.application-status', $this->application->public_id), $input)->assertSessionHasErrors('status');
        $this->assertSame(2, $this->candidate->notifications()->count());
        Queue::assertPushed(SendApplicationReceipt::class, 1);
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->change('rejected')->assertSessionHasNoErrors();
        $this->assertSame('rejected', $this->application->fresh()->status->value);
        $this->assertSame(3, $this->candidate->notifications()->count());
        $this->flushSession();
        $this->actingAs($this->candidate)->get(route('candidate.notifications'))->assertOk()->assertSee('Từ chối');
    }
}
