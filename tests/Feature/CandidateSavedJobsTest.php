<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Job;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\RecruiterJobService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class CandidateSavedJobsTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    private User $owner;

    private Job $job;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = new User;
        $this->owner->forceFill([
            'name' => 'Saved jobs owner', 'email' => Str::uuid().'@test.invalid',
            'password' => 'Password123', 'role' => 'recruiter', 'status' => 'active',
        ])->save();
        $this->companyFixture($this->owner->id);
        $this->input = $this->completeInput();
        $this->job = app(RecruiterJobService::class)->create($this->owner, $this->input);
        $this->publishFixture($this->job);
    }

    private function saveJob(): void
    {
        $this->post(route('candidate.save', $this->job->id), ['saved' => 1])->assertSessionHasNoErrors();
    }

    public function test_save_remove_are_idempotent_and_buttons_share_database_state(): void
    {
        $this->saveJob();
        $savedAt = SavedJob::firstOrFail()->created_at;
        $this->saveJob();
        $this->assertSame(1, SavedJob::count());
        $this->assertEquals($savedAt, SavedJob::first()->created_at);
        foreach (['jobs', 'candidate.saved'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('aria-pressed="true"', false);
        }
        $this->get(route('jobs.show', $this->job->id))->assertOk()->assertSee('aria-pressed="true"', false);
        $this->post(route('candidate.save', $this->job->id), ['saved' => 0])->assertSessionHasNoErrors();
        $this->delete(route('candidate.unsave', $this->job->id))->assertSessionHasNoErrors();
        $this->assertSame(0, SavedJob::count());
        $this->get(route('jobs.show', $this->job->id))->assertOk()->assertSee('aria-pressed="false"', false);
    }

    public function test_roles_guest_locked_candidate_and_idor_are_blocked(): void
    {
        $candidate = auth()->user();
        $this->saveJob();
        foreach ([UserRole::Recruiter, UserRole::Admin] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            $this->post(route('candidate.save', $this->job->id), ['saved' => 1])->assertForbidden();
        }
        $this->flushSession();
        $this->loginDemoUser(UserRole::Candidate);
        $this->delete(route('candidate.unsave', $this->job->id), ['candidate_id' => $candidate->id])->assertSessionHasNoErrors();
        $this->assertSame(1, SavedJob::where('candidate_id', $candidate->id)->count());
        DB::table('users')->where('id', auth()->id())->update(['status' => 'locked']);
        $this->postJson(route('candidate.save', $this->job->id), ['saved' => 1])->assertForbidden();
        $this->get(route('jobs.show', $this->job->id))->assertOk()->assertSee('Đăng nhập để lưu việc làm');
        $this->post(route('candidate.save', $this->job->id), ['saved' => 1])->assertRedirect(route('login'));
    }

    public function test_saved_list_is_scoped_to_candidate_and_has_no_demo(): void
    {
        $this->saveJob();
        $this->get(route('candidate.saved'))->assertOk()->assertViewHas('savedJobs', fn ($jobs) => $jobs->total() === 1)
            ->assertSee('Đã lưu:')->assertDontSee('Thêm/bỏ tin ẩn minh họa');
        $this->flushSession();
        $this->loginDemoUser(UserRole::Candidate);
        $this->get(route('candidate.saved'))->assertOk()->assertViewHas('savedJobs', fn ($jobs) => $jobs->total() === 0)
            ->assertSee('Chưa có việc làm đã lưu');
    }

    public function test_closed_expired_and_hidden_jobs_preserve_bookmark_without_exposing_content(): void
    {
        $this->saveJob();
        foreach (['closed', 'paused', 'open'] as $status) {
            DB::table('jobs')->where('id', $this->job->id)->update(['status' => $status, 'expires_at' => now()->subDay()]);
            $response = $this->get(route('candidate.saved'))->assertOk()->assertSee($this->input['title'])
                ->assertSee('Không nhận ứng tuyển')->assertDontSee($this->input['description']);
            $this->assertFalse($response->viewData('savedJobs')->first()['card']['can_view']);
            $this->get(route('jobs.show', $this->job->id))->assertNotFound();
            $this->post(route('candidate.save', $this->job->id), ['saved' => 1])->assertSessionHasErrors('saved');
        }
        DB::table('jobs')->where('id', $this->job->id)->update(['status' => 'blocked', 'status_before_block' => 'open']);
        $this->get(route('candidate.saved'))->assertOk()->assertSee('Công việc không khả dụng')->assertDontSee($this->input['title']);
        DB::table('jobs')->where('id', $this->job->id)->update(['status' => 'open', 'status_before_block' => null, 'expires_at' => now()->addDay()]);
        DB::table('companies')->where('id', $this->job->company_id)->update(['status' => 'locked']);
        $this->get(route('candidate.saved'))->assertOk()->assertDontSee($this->input['title']);
        $this->delete(route('candidate.unsave', $this->job->id))->assertSessionHasNoErrors();
        $this->assertSame(0, SavedJob::count());
    }

    public function test_only_published_approved_version_is_displayed(): void
    {
        $this->saveJob();
        $service = app(RecruiterJobService::class);
        $first = $service->latest($this->job);
        $service->update($this->owner, $this->job->id, ['title' => 'Private pending V2'] + $this->input + [
            'version_id' => $first->id, 'updated_at' => $first->getRawOriginal('updated_at'),
        ]);
        $second = $service->latest($this->job);
        $service->submit($this->owner, $this->job->id, ['version_id' => $second->id, 'updated_at' => $second->getRawOriginal('updated_at')]);
        $this->get(route('candidate.saved'))->assertOk()->assertSee($first->title)->assertDontSee('Private pending V2');
        $this->publishFixture($this->job);
        $this->get(route('candidate.saved'))->assertOk()->assertSee('Private pending V2')->assertDontSee($first->title);
        $this->assertSame(1, SavedJob::count());
    }
}
