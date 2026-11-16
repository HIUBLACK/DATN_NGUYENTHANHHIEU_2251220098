<?php

namespace Tests\Auth;

use App\Enums\ApplicationStatus;
use App\Enums\CompanyStatus;
use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobVersion;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    private function user(int $id, UserRole $role): User
    {
        return (new User)->forceFill(['id' => $id, 'role' => $role, 'status' => UserStatus::Active]);
    }

    private function job(User $recruiter): Job
    {
        $company = (new Company)->forceFill(['id' => 10, 'recruiter_id' => $recruiter->id, 'status' => CompanyStatus::Active]);
        $company->setRelation('recruiter', $recruiter);
        $version = (new JobVersion)->forceFill([
            'id' => 30, 'job_id' => 20, 'review_status' => JobReviewStatus::Approved,
            'application_deadline' => now('Asia/Ho_Chi_Minh')->addDay()->toDateString(),
        ]);
        $job = (new Job)->forceFill(['id' => 20, 'company_id' => 10, 'status' => JobStatus::Open, 'published_version_id' => 30, 'expires_at' => now()->addDays(2)]);

        return $job->setRelation('company', $company)->setRelation('publishedVersion', $version);
    }

    public function test_owner_and_role_policies_block_idor_and_admin_business_mutations(): void
    {
        $owner = $this->user(1, UserRole::Recruiter);
        $outsider = $this->user(2, UserRole::Recruiter);
        $candidate = $this->user(3, UserRole::Candidate);
        $admin = $this->user(4, UserRole::Admin);
        $job = $this->job($owner);
        $company = $job->company;
        $this->assertTrue(Gate::forUser($owner)->allows('update', $company));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $company));
        $this->assertFalse(Gate::forUser($candidate)->allows('update', $company));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $company));
        $this->assertTrue(Gate::forUser($admin)->allows('moderate', $company));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $job));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $job));
        $this->assertFalse(Gate::forUser($candidate)->allows('create', [Job::class, $company]));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $job));
        $this->assertTrue(Gate::forUser($admin)->allows('moderate', $job));
        $resume = (new Resume)->forceFill(['id' => 5, 'candidate_id' => $candidate->id]);
        $this->assertTrue(Gate::forUser($candidate)->allows('view', $resume));
        $this->assertFalse(Gate::forUser($this->user(6, UserRole::Candidate))->allows('view', $resume));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $resume));
        $this->assertSame(404, Gate::forUser($owner)->inspect('view', $resume)->status());
        $this->assertFalse(Gate::forUser($admin)->allows('view', $resume));
        $candidate->status = UserStatus::Locked;
        $this->assertFalse(Gate::forUser($candidate)->allows('update', $resume));
    }

    public function test_application_access_keeps_private_snapshot_and_notes_out_of_admin_projection(): void
    {
        $recruiter = $this->user(1, UserRole::Recruiter);
        $candidate = $this->user(2, UserRole::Candidate);
        $admin = $this->user(3, UserRole::Admin);
        $application = (new Application)->forceFill(['candidate_id' => $candidate->id, 'status' => ApplicationStatus::Applied]);
        $application->setRelation('job', $this->job($recruiter));
        $this->assertTrue(Gate::forUser($candidate)->allows('view', $application));
        $this->assertTrue(Gate::forUser($recruiter)->allows('viewSnapshot', $application));
        $this->assertTrue(Gate::forUser($candidate)->allows('viewSnapshot', $application));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $application));
        $this->assertFalse(Gate::forUser($admin)->allows('viewSnapshot', $application));
        $this->assertFalse(Gate::forUser($candidate)->allows('viewNotes', $application));
        $this->assertFalse(Gate::forUser($admin)->allows('viewNotes', $application));
        $this->assertFalse(Gate::forUser($this->user(4, UserRole::Recruiter))->allows('view', $application));
        $this->assertSame(404, Gate::forUser($this->user(4, UserRole::Recruiter))->inspect('view', $application)->status());
        $this->assertFalse(Gate::forUser($this->user(5, UserRole::Candidate))->allows('view', $application));
        $this->assertFalse(Gate::forUser($admin)->allows('changeStatus', $application));
        $this->assertTrue(Gate::forUser($recruiter)->allows('changeStatus', $application));
        $this->assertTrue(Gate::forUser($candidate)->allows('withdraw', $application));
        $application->status = ApplicationStatus::Hired;
        $this->assertFalse(Gate::forUser($candidate)->allows('withdraw', $application));
        $this->assertFalse(Gate::forUser($recruiter)->allows('changeStatus', $application));
    }

    public function test_public_job_checks_all_publication_conditions(): void
    {
        $recruiter = $this->user(1, UserRole::Recruiter);
        $job = $this->job($recruiter);
        $this->assertTrue(Gate::forUser(null)->allows('viewPublic', $job));
        foreach ([JobStatus::Draft, JobStatus::Paused, JobStatus::Closed, JobStatus::Blocked] as $status) {
            $job->status = $status;
            $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        }
        $job->status = JobStatus::Open;
        $job->publishedVersion->review_status = JobReviewStatus::Pending;
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        $job->publishedVersion->review_status = JobReviewStatus::Approved;
        $job->publishedVersion->job_id = 999;
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        $job->publishedVersion->job_id = $job->id;
        $job->company->status = CompanyStatus::Locked;
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        $job->company->status = CompanyStatus::Active;
        $recruiter->status = UserStatus::Locked;
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        $recruiter->status = UserStatus::Active;
        $job->expires_at = now()->subMinute();
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
        $job->expires_at = now()->addDay();
        $job->publishedVersion->application_deadline = now('Asia/Ho_Chi_Minh')->subDay()->toDateString();
        $this->assertFalse(Gate::forUser(null)->allows('viewPublic', $job));
    }
}
