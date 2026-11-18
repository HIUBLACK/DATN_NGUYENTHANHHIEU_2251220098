<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Job;
use App\Models\JobModerationLog;
use App\Models\JobVersion;
use App\Services\JobModerationService;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class JobModerationTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Recruiter;

    private $recruiter;

    private $admin;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recruiter = auth()->user();
        $this->companyFixture($this->recruiter->id);
        $this->input = $this->completeInput();
        $this->loginDemoUser(UserRole::Admin);
        $this->admin = auth()->user();
    }

    private function pending(?Job $job = null): JobVersion
    {
        $service = app(RecruiterJobService::class);
        if ($job) {
            $latest = $service->latest($job);
            $service->update($this->recruiter, $job->id, ['title' => 'Replacement V2'] + $this->input + ['version_id' => $latest->id, 'updated_at' => $latest->getRawOriginal('updated_at')]);
        } else {
            $job = $service->create($this->recruiter, $this->input);
        }
        $version = $service->latest($job);
        $service->submit($this->recruiter, $job->id, ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);

        return $version->fresh();
    }

    private function token(JobVersion $version): array
    {
        $version->refresh();

        return ['updated_at' => $version->getRawOriginal('updated_at'), 'job_updated_at' => $version->job->getRawOriginal('updated_at')];
    }

    private function decide(JobVersion $version, string $action = 'approve', array $extra = [])
    {
        return $this->post(route('admin.moderation.decide', [$version->id, $action]), $extra + $this->token($version));
    }

    public function test_only_active_admin_can_decide(): void
    {
        $version = $this->pending();
        foreach ([UserRole::Candidate, UserRole::Recruiter] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            $this->get(route('admin.jobs.queue'))->assertForbidden();
            $this->decide($version)->assertForbidden();
            $this->decide($version, 'reject', ['reason' => 'No'])->assertForbidden();
        }
        Auth::logout();
        $this->decide($version)->assertRedirect(route('login'));
        $this->flushSession();
        $this->actingAs($this->admin);
        $this->admin->status = 'locked';
        $this->admin->save();
        $this->decide($version)->assertRedirect(route('login'));
        $this->assertSame('pending', $version->fresh()->review_status->value);
        $this->assertSame(0, JobModerationLog::count());
    }

    public function test_approval_of_new_job_records_actor_and_publication(): void
    {
        $version = $this->pending();
        $this->get(route('admin.jobs.queue', ['q' => $version->title, 'status' => 'draft']))->assertOk()->assertSee($version->title);
        $this->get(route('admin.moderation.show', $version->id))->assertOk()->assertSee($version->requirements);
        $this->get(route('jobs.show', $version->job_id))->assertNotFound();
        $this->decide($version, 'approve', ['title' => 'Forged', 'published_version_id' => 999])->assertSessionHasNoErrors();
        $version->refresh();
        $this->assertSame('approved', $version->review_status->value);
        $this->assertSame($this->input['title'], $version->title);
        $this->assertSame($version->id, $version->job->published_version_id);
        $this->assertSame('open', $version->job->status->value);
        $this->assertSame($this->admin->id, $version->moderationLog->admin_id);
        $this->assertSame('approve', $version->moderationLog->action);
        $expected = CarbonImmutable::parse($version->application_deadline->format('Y-m-d'), 'Asia/Ho_Chi_Minh')->addDay()->utc();
        $this->assertTrue($expected->equalTo($version->job->expires_at));
        $this->get(route('jobs.show', $version->job_id))->assertOk()->assertSee($version->title);
    }

    public function test_approval_of_v2_switches_publication_only_after_decision(): void
    {
        $first = $this->pending();
        $this->decide($first)->assertSessionHasNoErrors();
        $second = $this->pending($first->job->fresh());
        $this->get(route('jobs.show', $first->job_id))->assertOk()->assertSee($first->title)->assertDontSee('Replacement V2');
        $this->get(route('admin.moderation.show', $second->id))->assertOk()->assertSee('Đối chiếu bản đã duyệt V1');
        $this->decide($second)->assertSessionHasNoErrors();
        $this->get(route('jobs.show', $first->job_id))->assertOk()->assertSee('Replacement V2');
        $this->assertSame($second->id, $first->job->fresh()->published_version_id);
        $this->assertSame('approved', $first->fresh()->review_status->value);
        $this->assertSame(2, JobModerationLog::count());
    }

    public function test_rejection_keeps_v1_and_allows_recruiter_to_revise(): void
    {
        $first = $this->pending();
        $this->decide($first)->assertSessionHasNoErrors();
        $second = $this->pending($first->job->fresh());
        $before = $second->job->getAttributes();
        $this->decide($second, 'reject', ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->decide($second, 'reject', ['reason' => 'Cần làm rõ quyền lợi'])->assertSessionHasNoErrors();
        $this->assertSame($before, $second->job->fresh()->getAttributes());
        $this->assertSame('rejected', $second->fresh()->review_status->value);
        $this->assertSame('Cần làm rõ quyền lợi', $second->fresh()->moderationLog->reason);
        $this->get(route('jobs.show', $first->job_id))->assertOk()->assertSee($first->title)->assertDontSee('Replacement V2');
        $this->flushSession();
        $this->actingAs($this->recruiter);
        $this->get(route('recruiter.job-detail', $first->job_id))->assertOk()->assertSee('Cần làm rõ quyền lợi');
        $third = $this->pending($first->job->fresh());
        $this->assertSame(3, $third->version_number);
    }

    public function test_duplicate_nonpending_and_stale_decisions_are_rejected(): void
    {
        $version = $this->pending();
        $this->decide($version, 'approve', ['updated_at' => 'old'])->assertSessionHasErrors('moderation');
        $this->decide($version, 'approve', ['job_updated_at' => 'old'])->assertSessionHasErrors('moderation');
        $this->decide($version)->assertSessionHasNoErrors();
        $this->decide($version)->assertSessionHasErrors('moderation');
        $this->decide($version, 'reject', ['reason' => 'Late'])->assertSessionHasErrors('moderation');
        $second = $this->pending($version->job->fresh());
        $this->decide($second, 'reject', ['reason' => 'Review'])->assertSessionHasNoErrors();
        $this->decide($second, 'reject', ['reason' => 'Again'])->assertSessionHasErrors('moderation');
        $draft = app(RecruiterJobService::class)->create($this->recruiter, ['title' => 'Draft'])->versions()->first();
        $this->decide($draft)->assertSessionHasErrors('moderation');
        $this->assertSame(2, JobModerationLog::count());
    }

    public function test_atomicity_and_operational_states_are_preserved(): void
    {
        $version = $this->pending();
        JobModerationLog::creating(function () {
            throw new \RuntimeException('Simulated log failure');
        });
        try {
            app(JobModerationService::class)->decide($this->admin, $version->id, 'approve', $this->token($version));
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated log failure', $exception->getMessage());
        } finally {
            JobModerationLog::flushEventListeners();
        }
        $this->assertSame('pending', $version->fresh()->review_status->value);
        $this->assertNull($version->job->fresh()->published_version_id);
        $this->assertSame(0, JobModerationLog::count());
        foreach (['company', 'recruiter', 'job'] as $target) {
            $model = match ($target) {
                'company' => $version->job->company, 'recruiter' => $this->recruiter, default => $version->job
            };
            $old = $model->status;
            $model->status = $target === 'job' ? 'blocked' : 'locked';
            if ($target === 'job') {
                $model->status_before_block = 'draft';
            }
            $model->save();
            $this->decide($version)->assertSessionHasErrors('moderation');
            $model->status = $old;
            if ($target === 'job') {
                $model->status_before_block = null;
            }
            $model->save();
        }
        DB::table('skills')->where('id', $this->input['skills'][0])->update(['is_active' => false]);
        $this->decide($version)->assertSessionHasErrors('skills');
        DB::table('skills')->where('id', $this->input['skills'][0])->update(['is_active' => true]);
        DB::table('job_versions')->where('id', $version->id)->update(['application_deadline' => now('Asia/Ho_Chi_Minh')->subDay()->toDateString()]);
        $this->decide($version)->assertSessionHasErrors('application_deadline');
        $this->assertNull($version->job->fresh()->published_version_id);
        foreach (['paused', 'closed'] as $state) {
            $pending = $this->pending();
            DB::table('jobs')->where('id', $pending->job_id)->update(['status' => $state]);
            $this->decide($pending)->assertSessionHasNoErrors();
            $this->assertSame($state, $pending->job->fresh()->status->value);
            $this->assertFalse(app(PublicJobService::class)->query()->whereKey($pending->job_id)->exists());
        }
    }
}
