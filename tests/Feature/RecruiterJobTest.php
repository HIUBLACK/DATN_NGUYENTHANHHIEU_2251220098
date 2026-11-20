<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Job;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use App\Support\Provinces;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class RecruiterJobTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Recruiter;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->companyFixture(auth()->id());
    }

    private function createJob(array $input = []): Job
    {
        $response = $this->get(route('recruiter.job-create'))->assertOk();
        $key = $response->viewData('creationKey');
        $this->post(route('recruiter.job-store'), $input + ['title' => 'Draft title', 'creation_key' => $key])->assertSessionHasNoErrors()->assertRedirect();

        return Job::where('company_id', $this->company->id)->latest('id')->firstOrFail();
    }

    private function versionToken(Job $job): array
    {
        $version = $job->versions()->orderByDesc('version_number')->firstOrFail();

        return ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')];
    }

    public function test_unchanged_edit_and_reversed_salary_cannot_be_submitted(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob($input);
        $token = $this->versionToken($job);
        $this->put(route('recruiter.job-update', $job->id), $input + $token + ['intent' => 'submit'])
            ->assertSessionHasErrors('job');
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['salary_max' => '1']) + $token + ['intent' => 'submit'])
            ->assertSessionHasErrors('salary_max');
        $this->assertSame(1, $job->versions()->count());
        $this->assertSame('draft', $job->versions()->first()->review_status->value);
    }

    public function test_confirmed_create_is_atomic_and_repeated_confirmation_creates_no_duplicate(): void
    {
        $form = $this->get(route('recruiter.job-create'))->assertOk()->assertSee('job-review-modal');
        $data = ['title' => 'Confirmed draft', 'intent' => 'submit', 'creation_key' => $form->viewData('creationKey')];
        $this->post(route('recruiter.job-store'), $data)->assertSessionHasErrors('industry_id');
        $this->assertSame(0, Job::where('company_id', $this->company->id)->count());
        $data = array_replace($this->completeInput(), $data);
        $this->post(route('recruiter.job-store'), $data)->assertSessionHasNoErrors();
        $this->post(route('recruiter.job-store'), $data)->assertSessionHasNoErrors();
        $job = Job::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame(1, $job->versions()->count());
        $this->assertSame('pending', $job->versions()->first()->review_status->value);
        $this->assertNull($job->published_version_id);
    }

    public function test_confirmed_edit_uses_public_baseline_and_preserves_public_version(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob($input);
        $published = $this->publishFixture($job);
        $form = $this->get(route('recruiter.job-edit', $job->id))->assertOk();
        $this->assertSame($published->title, $form->viewData('baseline')['values']['title']);
        $token = $this->versionToken($job);
        $invalid = array_replace($input, ['intent' => 'submit', 'description' => '']);
        $this->put(route('recruiter.job-update', $job->id), $invalid + $token)->assertSessionHasErrors('description');
        $this->assertSame(1, $job->versions()->count());
        $data = array_replace($input, ['intent' => 'submit', 'title' => 'Unsaved replacement']) + $token;
        $this->put(route('recruiter.job-update', $job->id), $data)->assertSessionHasNoErrors();
        $this->put(route('recruiter.job-update', $job->id), $data)->assertSessionHasErrors('job');
        $this->assertSame(2, $job->versions()->count());
        $this->assertSame($published->id, $job->fresh()->published_version_id);
        $this->assertSame($input['title'], $published->fresh()->title);
        $this->assertSame('pending', app(RecruiterJobService::class)->latest($job)->review_status->value);
    }

    public function test_position_province_and_inferred_group_replace_free_text_inputs(): void
    {
        $input = $this->completeInput();
        unset($input['experience_summary'], $input['job_category_id']);
        $job = $this->createJob($input + ['position' => 'Forged position', 'job_category_id' => 999999]);
        $version = $job->versions()->firstOrFail();
        $this->assertSame($input['job_position_id'], $version->job_position_id);
        $this->assertSame($version->positionCatalog->name, $version->position);
        $this->assertSame('48', $version->province_code);
        $this->assertNull($version->experience_summary);
        $this->assertCount(34, Provinces::ALL);
        $this->get(route('recruiter.job-edit', $job->id))->assertOk()
            ->assertSee('name="job_position_id"', false)->assertSee('name="province_code"', false)
            ->assertDontSee('name="job_category_id"', false)->assertDontSee('name="experience_summary"', false);
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['province_code' => '99']) + $this->versionToken($job))
            ->assertSessionHasErrors('province_code');
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['industry_id' => [$input['industry_id']]]) + $this->versionToken($job))
            ->assertSessionHasErrors('industry_id');
        DB::table('job_positions')->where('id', $input['job_position_id'])->update(['is_active' => false]);
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasErrors('job_position_id');
        $this->get(route('recruiter.job-edit', $job->id))->assertOk()->assertSee($version->position);
        DB::table('job_positions')->where('id', $input['job_position_id'])->update(['is_active' => true]);
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasNoErrors();
        $this->assertSame('pending', $version->fresh()->review_status->value);
    }

    public function test_title_only_draft_ignores_system_fields_and_duplicate_post(): void
    {
        $form = $this->get(route('recruiter.job-create'))->assertOk();
        $data = ['title' => 'Only title', 'creation_key' => $form->viewData('creationKey'), 'company_id' => 999999, 'status' => 'open', 'review_status' => 'approved', 'published_version_id' => 999];
        $this->post(route('recruiter.job-store'), $data)->assertSessionHasNoErrors();
        $this->post(route('recruiter.job-store'), $data)->assertSessionHasNoErrors();
        $job = Job::firstOrFail();
        $this->assertSame(1, Job::count());
        $this->assertSame($this->company->id, $job->company_id);
        $this->assertSame('draft', $job->status->value);
        $this->assertNull($job->published_version_id);
        $version = $job->versions()->first();
        foreach (['position', 'description', 'salary_min', 'quantity', 'job_specialization_id', 'application_deadline'] as $field) {
            $this->assertNull($version->$field);
        }
        $this->assertSame('draft', $version->review_status->value);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'job.create')->count());
        $this->get(route('recruiter.jobs'))->assertOk()->assertSee('Only title');
        $this->get(route('recruiter.job-preview', $job->id))->assertOk()->assertSee('Only title');
        $this->assertSame(1, $job->versions()->count());
    }

    public function test_owner_company_and_roles_are_enforced(): void
    {
        $job = $this->createJob();
        foreach ([UserRole::Candidate, UserRole::Admin, UserRole::Recruiter] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            $this->get(route('recruiter.job-create'))->assertForbidden();
            $this->put(route('recruiter.job-update', $job->id), ['title' => 'Forged'] + $this->versionToken($job))->assertForbidden();
            $this->get(route('recruiter.job-preview', $job->id))->assertForbidden();
        }
        Auth::logout();
        $this->get(route('recruiter.jobs'))->assertRedirect(route('login'));
        $this->post(route('recruiter.job-store'), ['title' => 'Guest'])->assertRedirect(route('login'));
    }

    public function test_locked_company_cannot_create_update_or_submit(): void
    {
        $job = $this->createJob();
        $this->company->status = 'locked';
        $this->company->save();
        $this->get(route('recruiter.job-create'))->assertForbidden();
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'No'] + $this->versionToken($job))->assertForbidden();
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertForbidden();
    }

    public function test_draft_updates_repeatedly_and_stale_form_cannot_overwrite(): void
    {
        $job = $this->createJob();
        $stale = $this->versionToken($job);
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'Changed', 'position' => 'Developer'] + $stale)->assertSessionHasNoErrors();
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'Stale'] + $stale)->assertSessionHasErrors('job');
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'Changed again'] + $this->versionToken($job))->assertSessionHasNoErrors();
        $this->assertSame(1, $job->versions()->count());
        $this->assertSame('Changed again', $job->versions()->first()->title);
        $this->assertNull($job->versions()->first()->position);
    }

    public function test_submit_requires_complete_active_catalogs_skills_and_deadline(): void
    {
        $job = $this->createJob();
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasErrors(['position', 'skills', 'industry_id']);
        $input = $this->completeInput();
        $this->put(route('recruiter.job-update', $job->id), $input + $this->versionToken($job))->assertSessionHasNoErrors();
        DB::table('skills')->where('id', $input['skills'][0])->update(['is_active' => false]);
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasErrors('skills');
        DB::table('skills')->where('id', $input['skills'][0])->update(['is_active' => true]);
        $version = $job->versions()->first();
        $version->application_deadline = now()->subDay()->toDateString();
        $version->save();
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasErrors('application_deadline');
        $this->assertSame(0, DB::table('job_moderation_logs')->count());
    }

    public function test_valid_submit_is_pending_and_cannot_be_edited_or_submitted_twice(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob($input);
        $token = $this->versionToken($job);
        $this->post(route('recruiter.job-submit', $job->id), $token)->assertSessionHasNoErrors();
        $this->post(route('recruiter.job-submit', $job->id), $token)->assertSessionHasErrors('job');
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'Tampered'] + $this->versionToken($job))->assertSessionHasErrors('job');
        $version = $job->versions()->first();
        $this->assertSame('pending', $version->review_status->value);
        $this->assertNotNull($version->submitted_at);
        $this->assertSame($input['skills'], $version->skills->pluck('id')->all());
        $this->assertNull($job->fresh()->published_version_id);
        $this->get(route('jobs.show', $job->id))->assertNotFound();
        $this->get(route('jobs'))->assertDontSee($input['title']);
    }

    public function test_approved_content_and_skills_stay_immutable_while_new_draft_is_saved(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob($input);
        $published = $this->publishFixture($job);
        $this->get(route('recruiter.job-preview', $job->id))->assertOk();
        $this->assertSame(1, $job->versions()->count());
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['title' => 'SECRET DRAFT', 'description' => 'PRIVATE REPLACEMENT', 'skills' => []]) + $this->versionToken($job))->assertSessionHasNoErrors();
        $latest = app(RecruiterJobService::class)->latest($job);
        $this->assertSame(2, $latest->version_number);
        $this->assertSame('draft', $latest->review_status->value);
        $this->assertSame($published->id, $job->fresh()->published_version_id);
        $this->assertSame($input['title'], $published->fresh()->title);
        $this->assertSame($input['skills'], $published->fresh()->skills->pluck('id')->all());
        $this->assertCount(0, $latest->skills);
        foreach ([route('jobs.show', $job->id), route('jobs'), route('home'), route('companies.show', $this->company->public_id)] as $url) {
            $this->get($url)->assertOk()->assertSee($input['title'])->assertDontSee('SECRET DRAFT')->assertDontSee('PRIVATE REPLACEMENT');
        }
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['title' => 'SECRET PENDING']) + $this->versionToken($job))->assertSessionHasNoErrors();
        $this->post(route('recruiter.job-submit', $job->id), $this->versionToken($job))->assertSessionHasNoErrors();
        $this->get(route('jobs.show', $job->id))->assertSee($input['title'])->assertDontSee('SECRET PENDING');
    }

    public function test_rejected_version_is_preserved_and_replaced_with_new_draft(): void
    {
        $job = $this->createJob($this->completeInput());
        $version = $job->versions()->first();
        DB::table('job_versions')->where('id', $version->id)->update(['review_status' => 'rejected', 'submitted_at' => now(), 'reviewed_at' => now()]);
        DB::table('job_moderation_logs')->insert(['job_id' => $job->id, 'job_version_id' => $version->id, 'admin_id' => auth()->id(), 'action' => 'reject', 'reason' => 'Please clarify duties', 'created_at' => now()]);
        $this->get(route('recruiter.job-detail', $job->id))->assertOk()->assertSee('Please clarify duties');
        $this->get(route('jobs.show', $job->id))->assertNotFound();
        $this->put(route('recruiter.job-update', $job->id), ['title' => 'New draft'] + $this->versionToken($job))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $version->fresh()->review_status->value);
        $this->assertSame(2, $job->versions()->max('version_number'));
        $this->assertSame(1, DB::table('job_moderation_logs')->count());
    }

    public function test_catalog_relationships_duplicates_and_inactive_new_values_are_rejected(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob();
        $token = $this->versionToken($job);
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['skills' => [$input['skills'][0], $input['skills'][0]]]) + $token)->assertSessionHasErrors('skills.0');
        $this->put(route('recruiter.job-update', $job->id), array_replace($input, ['job_specialization_id' => 99999999]) + $token)->assertSessionHasErrors('job_specialization_id');
        DB::table('job_specializations')->where('id', $input['job_specialization_id'])->update(['is_active' => false]);
        $this->put(route('recruiter.job-update', $job->id), $input + $token)->assertSessionHasErrors('job_specialization_id');
    }

    public function test_pause_reopen_close_are_logged_without_duplicate_or_review_changes(): void
    {
        $job = $this->createJob($this->completeInput());
        $published = $this->publishFixture($job);
        foreach (['pause', 'pause', 'reopen', 'close', 'close'] as $action) {
            $this->post(route('recruiter.job-transition', [$job->id, $action]))->assertSessionHasNoErrors();
        }
        $this->assertSame(4, DB::table('activity_logs')->count());
        $this->assertSame('approved', $published->fresh()->review_status->value);
        $this->post(route('recruiter.job-transition', [$job->id, 'reopen']))->assertSessionHasErrors('job');
        $this->assertSame('closed', $job->fresh()->status->value);
        $this->get(route('jobs.show', $job->id))->assertNotFound();
    }

    public function test_expired_blocked_and_locked_owners_cannot_reopen_or_appear_publicly(): void
    {
        $job = $this->createJob($this->completeInput());
        $this->publishFixture($job);
        $this->post(route('recruiter.job-transition', [$job->id, 'pause']))->assertSessionHasNoErrors();
        DB::table('jobs')->where('id', $job->id)->update(['expires_at' => now()->subMinute()]);
        $this->post(route('recruiter.job-transition', [$job->id, 'reopen']))->assertSessionHasErrors('job');
        DB::table('jobs')->where('id', $job->id)->update(['status' => 'blocked', 'status_before_block' => 'paused']);
        $this->post(route('recruiter.job-transition', [$job->id, 'reopen']))->assertForbidden();
        $this->assertSame(0, app(PublicJobService::class)->query()->count());
        DB::table('jobs')->where('id', $job->id)->update(['status' => 'open', 'status_before_block' => null, 'expires_at' => now()->addDay()]);
        DB::table('users')->where('id', auth()->id())->update(['status' => 'locked']);
        $this->assertSame(0, app(PublicJobService::class)->query()->count());
    }

    public function test_real_search_filters_combine_and_use_only_published_version(): void
    {
        $input = $this->completeInput();
        $job = $this->createJob($input);
        $this->publishFixture($job);
        $filters = ['occupationId' => $input['job_category_id'], 'specialtyId' => $input['job_specialization_id'], 'location' => $input['location'], 'mode' => 'remote', 'salary' => 15];
        $this->get(route('jobs', $filters))->assertOk()->assertSee($input['title']);
        $this->get(route('jobs', array_replace($filters, ['location' => 'Wrong location'])))->assertOk()->assertDontSee($input['title']);
        $this->get(route('jobs.show', $job->id))->assertDontSee(route('candidate.apply', $job->id))->assertDontSee(route('candidate.save', $job->id));
    }
}
