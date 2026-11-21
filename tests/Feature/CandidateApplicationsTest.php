<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendApplicationReceipt;
use App\Models\Application;
use App\Models\Resume;
use App\Models\User;
use App\Services\CandidateApplicationService;
use App\Services\RecruiterJobService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class CandidateApplicationsTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;
    use PdfFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(): Resume
    {
        $this->post(route('candidate.upload'), ['pdf' => $this->pdf()])->assertSessionHasNoErrors();

        return Resume::forCandidate(auth()->id())->latest('id')->firstOrFail();
    }

    private function job()
    {
        $owner = new User;
        $owner->forceFill(['name' => 'Recruiter', 'email' => Str::uuid().'@test.invalid', 'password' => 'Password123', 'role' => UserRole::Recruiter, 'status' => 'active'])->save();
        $this->companyFixture($owner->id);
        $job = app(RecruiterJobService::class)->create($owner, $this->completeInput());
        $this->publishFixture($job);

        return $job;
    }

    private function input($job, Resume $resume, int $attempt = 1): array
    {
        return ['resume' => $resume->public_id, 'job_version_id' => $job->published_version_id, 'attempt_no' => $attempt, 'confirmation' => 1];
    }

    public function test_pdf_validation_limit_and_primary_invariant(): void
    {
        $this->post(route('candidate.upload'), ['pdf' => UploadedFile::fake()->createWithContent('fake.pdf', '%PDF-1.4 fake %%EOF')])->assertSessionHasErrors('pdf');
        $this->post(route('candidate.upload'), ['pdf' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('pdf');
        $first = $this->upload();
        $this->assertTrue($first->is_primary);
        $this->post(route('candidate.resume-action', [$first->public_id, 'main']))->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->is_primary);
        for ($i = 1; $i < 10; $i++) {
            $last = $this->upload();
        }
        $this->post(route('candidate.upload'), ['pdf' => $this->pdf()])->assertSessionHasErrors('pdf');
        $this->assertSame(10, Resume::count());
        $this->post(route('candidate.resume-action', [$first->public_id, 'delete']))->assertSessionHasErrors('replacement');
        $this->post(route('candidate.resume-action', [$first->public_id, 'delete']), ['replacement' => $last->public_id])->assertSessionHasNoErrors();
        $this->assertTrue($last->fresh()->is_primary);
        $this->assertSame(1, Resume::where('is_primary', true)->count());
        $this->get(route('candidate.resumes'))->assertOk()->assertDontSee('Chưa chạy AI');
    }

    public function test_ownership_protects_resumes_applications_and_snapshots(): void
    {
        $resume = $this->upload();
        $job = $this->job();
        $application = app(CandidateApplicationService::class)->apply(auth()->user(), $job->id, $this->input($job, $resume));
        $this->flushSession();
        $this->loginDemoUser(UserRole::Candidate);
        $this->get(route('candidate.pdf', $resume->public_id))->assertNotFound();
        $this->post(route('candidate.replace', $resume->public_id), ['pdf' => $this->pdf()])->assertNotFound();
        $this->post(route('candidate.resume-action', [$resume->public_id, 'delete']))->assertNotFound();
        $this->get(route('candidate.snapshot', $application->public_id))->assertNotFound();
        $this->get(route('candidate.application-detail', $application->public_id))->assertNotFound();
        $this->post(route('candidate.withdraw', $application->public_id))->assertNotFound();
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertNotFound();
        $this->flushSession();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->post(route('candidate.upload'), ['pdf' => $this->pdf()])->assertForbidden();
    }

    public function test_application_snapshots_history_notifications_and_duplicate_protection(): void
    {
        $resume = $this->upload();
        $job = $this->job();
        $this->get(route('jobs.show', $job->id))->assertOk()->assertSee('apply-real-modal');
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasNoErrors();
        $application = Application::firstOrFail();
        $revision = $resume->currentRevision()->first();
        $this->assertSame($job->published_version_id, $application->job_version_id);
        $this->assertSame($revision->file_hash, $application->resume_snapshot_hash);
        $this->assertNotSame($revision->file_path, $application->resume_snapshot_path);
        Storage::disk('local')->assertExists($application->resume_snapshot_path);
        $this->assertSame(1, $application->statusHistories()->count());
        $this->assertSame(1, auth()->user()->notifications()->count());
        Queue::assertPushed(SendApplicationReceipt::class, 1);
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        $this->assertSame(1, Application::count());
        $this->get(route('candidate.application-detail', $application->public_id))->assertOk();
        $this->get(route('candidate.notifications'))->assertOk();
        $this->post(route('candidate.read', 'all'))->assertSessionHasNoErrors();
        $this->assertSame(0, auth()->user()->unreadNotifications()->count());
        $this->get(route('candidate.applications'))->assertOk();
        $this->get(route('candidate.snapshot', $application->public_id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unavailable_jobs_and_stale_version_cannot_receive_applications(): void
    {
        $resume = $this->upload();
        $job = $this->job();
        foreach (['draft', 'paused', 'closed'] as $status) {
            DB::table('jobs')->where('id', $job->id)->update(['status' => $status]);
            $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        }
        DB::table('jobs')->where('id', $job->id)->update(['status' => 'blocked', 'status_before_block' => 'open']);
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        DB::table('jobs')->where('id', $job->id)->update(['status' => 'open', 'status_before_block' => null, 'expires_at' => now()->subDay()]);
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        DB::table('jobs')->where('id', $job->id)->update(['expires_at' => now()->addDay()]);
        DB::table('companies')->where('id', $job->company_id)->update(['status' => 'locked']);
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        DB::table('companies')->where('id', $job->company_id)->update(['status' => 'active']);
        DB::table('users')->where('id', $job->company->recruiter_id)->update(['status' => 'locked']);
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasErrors('application');
        DB::table('users')->where('id', $job->company->recruiter_id)->update(['status' => 'active']);
        $this->post(route('candidate.apply', $job->id), array_replace($this->input($job, $resume), ['job_version_id' => 999999]))->assertSessionHasErrors('application');
        $this->assertSame(0, Application::count());
    }

    public function test_withdrawal_reapplication_limit_and_final_states(): void
    {
        $resume = $this->upload();
        $job = $this->job();
        $input = $this->input($job, $resume);
        $this->post(route('candidate.apply', $job->id), $input)->assertSessionHasNoErrors();
        $first = Application::firstOrFail();
        DB::table('applications')->where('id', $first->id)->update(['status' => 'interview']);
        $this->post(route('candidate.withdraw', $first->public_id))->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $first->fresh()->status->value);
        $this->post(route('candidate.apply', $job->id), $input)->assertSessionHasErrors('application');
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume, 2))->assertSessionHasNoErrors();
        $second = Application::latest('id')->first();
        foreach (['hired', 'rejected'] as $status) {
            DB::table('applications')->where('id', $second->id)->update(['status' => $status]);
            $this->post(route('candidate.withdraw', $second->public_id))->assertForbidden();
        }
        DB::table('applications')->where('id', $second->id)->update(['status' => 'reviewing']);
        $this->post(route('candidate.withdraw', $second->public_id))->assertSessionHasNoErrors();
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume, 2))->assertSessionHasErrors('application');
        $this->assertSame(2, Application::count());
        $this->assertSame(2, $first->statusHistories()->count());
    }

    public function test_replacement_and_deletion_preserve_submitted_pdf(): void
    {
        $resume = $this->upload();
        $job = $this->job();
        $this->post(route('candidate.apply', $job->id), $this->input($job, $resume))->assertSessionHasNoErrors();
        $application = Application::firstOrFail();
        $snapshot = Storage::disk('local')->get($application->resume_snapshot_path);
        $this->post(route('candidate.replace', $resume->public_id), ['pdf' => $this->pdf('Changed CV')])->assertSessionHasNoErrors();
        $this->assertSame(2, $resume->fresh()->current_revision);
        $this->assertSame(2, $resume->revisions()->count());
        $this->post(route('candidate.resume-action', [$resume->public_id, 'delete']))->assertSessionHasNoErrors();
        $this->assertSame(0, Resume::count());
        $this->assertSame(1, $application->fresh()->resume_revision);
        $this->assertSame($snapshot, Storage::disk('local')->get($application->resume_snapshot_path));
        $this->get(route('candidate.pdf', $resume->public_id))->assertNotFound();
        $this->get(route('candidate.snapshot', $application->public_id))->assertOk();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->post(route('candidate.withdraw', $application->public_id))->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $application->fresh()->status->value);
        $this->assertSame(2, auth()->user()->notifications()->count());
    }
}
