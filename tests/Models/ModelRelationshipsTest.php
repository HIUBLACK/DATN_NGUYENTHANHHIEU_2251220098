<?php

namespace Tests\Models;

use App\Enums\AiEvaluationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\CompanyStatus;
use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Enums\RecommendationStatus;
use App\Enums\ResumeParsingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\ApplicationAiEvaluation;
use App\Models\ApplicationNote;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobModerationLog;
use App\Models\JobVersion;
use App\Models\JobVersionSkill;
use App\Models\RecommendationItem;
use App\Models\RecommendationRun;
use App\Models\Resume;
use App\Models\ResumeRevision;
use App\Models\ResumeSkill;
use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Support\JobCatalog;

class ModelRelationshipsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', $database)) {
            throw new \RuntimeException('Run php tests/Database/models.php; never write model fixtures to db_job.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function persist(string $class, array $attributes): Model
    {
        $model = new $class;
        // Trusted fixtures bypass mass assignment; application requests must not do this.
        $model->forceFill($attributes)->save();

        return $model->refresh();
    }

    private function user(UserRole $role = UserRole::Candidate): User
    {
        return $this->persist(User::class, [
            'name' => 'Test User', 'email' => Str::uuid().'@test.invalid',
            'password' => 'test-password', 'role' => $role,
        ]);
    }


    private function jobGraph(): array
    {
        $recruiter = $this->user(UserRole::Recruiter);
        $industry = JobCatalog::find('industries', 1);
        $level = JobCatalog::find('levels', 1);
        $category = JobCatalog::find('categories', 1);
        $specialization = JobCatalog::find('specializations', 1);
        $company = $this->persist(Company::class, [
            'recruiter_id' => $recruiter->id, 'industry_id' => $industry->id, 'name' => 'Company',
            'slug' => (string) Str::uuid(), 'office_images' => [['path' => 'office/example.webp', 'caption' => 'Văn phòng']],
            'email' => 'company@test.invalid', 'phone' => '0900000000', 'address' => 'Test address',
        ]);
        $job = $this->persist(Job::class, ['company_id' => $company->id]);
        $version = $this->persist(JobVersion::class, [
            'job_id' => $job->id, 'version_number' => 1, 'title' => 'Backend', 'position' => 'Developer',
            'description' => 'Description', 'requirements' => 'PHP', 'benefits' => 'Benefits',
            'salary_min' => '15000000.00', 'salary_max' => '20000000.00', 'location' => 'Đà Nẵng',
            'work_mode' => 'onsite', 'employment_type' => 'full_time', 'quantity' => 1,
            'experience_summary' => '1–3 years', 'industry_id' => $industry->id, 'job_level_id' => $level->id,
            'job_specialization_id' => $specialization->id, 'application_deadline' => '2027-01-01',
        ]);

        return compact('recruiter', 'industry', 'level', 'category', 'specialization', 'company', 'job', 'version');
    }

    private function resumeGraph(?User $candidate = null): array
    {
        $candidate ??= $this->user();
        $resume = $this->persist(Resume::class, ['candidate_id' => $candidate->id, 'display_name' => 'My CV', 'current_revision' => 2]);
        $first = $this->persist(ResumeRevision::class, [
            'resume_id' => $resume->id, 'revision' => 1, 'file_path' => 'private/first.pdf',
            'original_filename' => 'first.pdf', 'file_size' => 100, 'mime_type' => 'application/pdf', 'file_hash' => str_repeat('a', 64),
        ]);
        $second = $this->persist(ResumeRevision::class, [
            'resume_id' => $resume->id, 'revision' => 2, 'file_path' => 'private/second.pdf',
            'original_filename' => 'second.pdf', 'file_size' => 200, 'mime_type' => 'application/pdf', 'file_hash' => str_repeat('b', 64),
        ]);

        return compact('candidate', 'resume', 'first', 'second');
    }

    private function application(array $jobs, array $resumes): Application
    {
        return $this->persist(Application::class, [
            'candidate_id' => $resumes['candidate']->id, 'job_id' => $jobs['job']->id,
            'job_version_id' => $jobs['version']->id, 'attempt_no' => 1,
            'resume_id' => $resumes['resume']->id, 'resume_revision' => 1,
            'resume_snapshot_path' => 'snapshots/test.pdf', 'resume_snapshot_hash' => str_repeat('a', 64),
            'resume_snapshot_filename' => 'first.pdf', 'resume_snapshot_size' => 100, 'applied_at' => now(),
        ]);
    }

    public function test_table_mapping_and_guarded_fields_match_live_schema(): void
    {
        $models = [
            User::class => 'users', CandidateProfile::class => 'candidate_profiles', Company::class => 'companies',
            Job::class => 'jobs', JobVersion::class => 'job_versions',
            JobVersionSkill::class => 'job_version_skills', JobModerationLog::class => 'job_moderation_logs', Resume::class => 'resumes',
            ResumeRevision::class => 'resume_revisions', ResumeSkill::class => 'resume_skills', Application::class => 'applications',
            ApplicationStatusHistory::class => 'application_status_histories', ApplicationNote::class => 'application_notes',
            SavedJob::class => 'saved_jobs', RecommendationRun::class => 'recommendation_runs', RecommendationItem::class => 'recommendation_items',
            ApplicationAiEvaluation::class => 'application_ai_evaluations', ActivityLog::class => 'activity_logs', DatabaseNotification::class => 'notifications',
        ];
        foreach ($models as $class => $table) {
            $model = new $class;
            $this->assertSame($table, $model->getTable());
            $this->assertSame('id', $model->getKeyName());
            $this->assertSame($class !== DatabaseNotification::class, $model->getIncrementing());
            if ($class === DatabaseNotification::class) {
                continue; // Framework model accepts trusted notification-channel payloads.
            }
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            foreach ($model->getFillable() as $field) {
                $this->assertContains($field, $columns);
            }
            foreach ($columns as $field) {
                if ($field === 'id' || str_ends_with($field, '_id') || in_array($field, [
                    'role', 'status', 'review_status', 'is_primary', 'current_revision', 'attempt_no',
                    'score', 'semantic_score', 'skill_score', 'processing_token', 'pending_slot', 'primary_slot',
                ], true)) {
                    $this->assertFalse($model->isFillable($field), "$class must guard $field");
                }
            }
        }
        $user = new User(['name' => 'Allowed', 'role' => 'admin', 'status' => 'locked']);
        $this->assertSame('Allowed', $user->name);
        $this->assertNull($user->role);
        $this->assertNull($user->status);
        $this->expectException(MassAssignmentException::class);
        new ApplicationAiEvaluation(['score' => 100]);
    }

    public function test_account_company_catalog_and_job_relationships_support_eager_loading(): void
    {
        $graph = $this->jobGraph();
        $candidate = $this->user();
        $profile = $this->persist(CandidateProfile::class, ['user_id' => $candidate->id, 'phone' => '0900000000']);
        $this->assertTrue($candidate->load('candidateProfile')->candidateProfile->is($profile));
        $this->assertTrue($profile->user->is($candidate));
        $this->assertTrue($graph['recruiter']->load('company')->company->is($graph['company']));
        $company = $graph['company']->load('recruiter', 'jobs');
        $this->assertTrue($company->recruiter->is($graph['recruiter']));
        $this->assertEquals($graph['industry'], $company->industry);
        $this->assertTrue($company->jobs->first()->is($graph['job']));
        $this->assertSame('Văn phòng', $company->office_images[0]['caption']);
        $this->assertSame(CompanyStatus::Active, $company->status);
        $this->assertTrue(Str::isUuid($company->public_id));
        $this->assertIsInt($company->id);
        $this->assertSame('id', $company->getRouteKeyName());
        $version = $graph['version']->load('job.company');
        $this->assertTrue($version->job->company->is($company));
        $this->assertEquals($graph['industry'], $version->industry);
        $this->assertEquals($graph['level'], $version->level);
        $this->assertEquals($graph['category'], $version->specialization->category);
        $this->assertSame('15000000.00', $version->salary_min);
        $this->assertSame('2027-01-01', $version->application_deadline->format('Y-m-d'));
        $this->assertSame(JobReviewStatus::Draft, $version->review_status);
        $this->assertTrue($graph['job']->versions->first()->is($version));
        $skill = JobCatalog::find('skills', 1);
        $pivot = $this->persist(JobVersionSkill::class, ['job_version_id' => $version->id, 'skill_id' => $skill->id, 'is_required' => true]);
        $this->assertSame($skill->id, $version->load('skillRequirements')->skills->first()->id);
        $this->assertInstanceOf(JobVersionSkill::class, $version->skills->first()->pivot);
        $this->assertTrue($version->skills->first()->pivot->is_required);
        $this->assertTrue($pivot->jobVersion->is($version));
        $this->assertEquals($skill, $pivot->skill);
        $this->assertTrue($version->skillRequirements->first()->is($pivot));
    }

    public function test_resume_queries_keep_revisions_separate_and_soft_deleted_sources_accessible(): void
    {
        $graph = $this->resumeGraph();
        $other = $this->resumeGraph();
        $skill = JobCatalog::find('skills', 1);
        $firstSkill = $this->persist(ResumeSkill::class, ['resume_id' => $graph['resume']->id, 'resume_revision' => 1, 'skill_id' => $skill->id, 'confidence_score' => '0.7500']);
        $secondSkill = $this->persist(ResumeSkill::class, ['resume_id' => $graph['resume']->id, 'resume_revision' => 2, 'skill_id' => $skill->id]);
        $this->persist(ResumeSkill::class, ['resume_id' => $other['resume']->id, 'resume_revision' => 1, 'skill_id' => $skill->id]);
        $this->assertSame([$firstSkill->id], $graph['first']->skills()->pluck('id')->all());
        $this->assertSame([$secondSkill->id], $graph['second']->skills()->pluck('id')->all());
        $this->assertSame('0.7500', $firstSkill->confidence_score);
        $this->assertTrue($firstSkill->revisionQuery()->first()->is($graph['first']));
        $this->assertTrue($firstSkill->resume->is($graph['resume']));
        $this->assertEquals($skill, $firstSkill->skill);
        $this->assertSame([$secondSkill->id], $graph['resume']->currentSkillsQuery()->pluck('id')->all());
        $this->assertTrue($graph['resume']->currentRevision()->first()->is($graph['second']));
        $this->assertCount(2, $graph['resume']->load('revisions', 'skills')->revisions);
        $this->assertCount(2, $graph['resume']->skills);
        $this->assertTrue($graph['resume']->candidate->is($graph['candidate']));
        $this->assertTrue($graph['candidate']->resumes->first()->is($graph['resume']));
        $this->assertSame(ResumeParsingStatus::Pending, $graph['first']->parsing_status);
        $this->assertArrayNotHasKey('file_path', $graph['first']->toArray());
        $this->assertNull(ResumeRevision::forRevision($other['resume']->id, 99)->first());
        $this->assertSame([$firstSkill->id], ResumeSkill::forRevision($graph['resume']->id, 1)->pluck('id')->all());
        $this->assertNull(Resume::forCandidate($other['candidate']->id)->find($graph['resume']->id));
        $application = $this->application($this->jobGraph(), $graph);
        $graph['resume']->delete();
        $this->assertNull(Resume::find($graph['resume']->id));
        $this->assertTrue(Resume::withTrashed()->find($graph['resume']->id)->trashed());
        $this->assertTrue($application->load('resume')->resume->trashed());
        $this->assertTrue($application->resumeForCandidateQuery()->first()->trashed());
        $this->assertTrue($graph['first']->resume->trashed());
        $this->assertTrue($application->resumeRevisionQuery()->first()->is($graph['first']));
        $graph['resume']->restore();
        $this->assertNotNull(Resume::find($graph['resume']->id));
    }

    public function test_applications_recommendations_and_evaluations_resolve_all_foreign_keys(): void
    {
        $jobs = $this->jobGraph();
        $resumes = $this->resumeGraph();
        $application = $this->application($jobs, $resumes);
        $note = $this->persist(ApplicationNote::class, ['application_id' => $application->id, 'recruiter_id' => $jobs['recruiter']->id, 'content' => 'Internal note']);
        $history = $this->persist(ApplicationStatusHistory::class, ['application_id' => $application->id, 'actor_id' => $resumes['candidate']->id, 'new_status' => ApplicationStatus::Applied]);
        $saved = $this->persist(SavedJob::class, ['candidate_id' => $resumes['candidate']->id, 'job_id' => $jobs['job']->id]);
        $run = $this->persist(RecommendationRun::class, [
            'candidate_id' => $resumes['candidate']->id, 'resume_id' => $resumes['resume']->id, 'resume_revision' => 1,
            'recommendation_generation' => 0, 'model_version' => 'test', 'formula_version' => 'v1', 'request_key' => str_repeat('c', 64),
        ]);
        $item = $this->persist(RecommendationItem::class, [
            'recommendation_run_id' => $run->id, 'job_id' => $jobs['job']->id, 'job_version_id' => $jobs['version']->id,
            'score' => '68.00', 'semantic_score' => '80.00', 'skill_score' => '50.00',
            'matched_skills' => [['skill_id' => 1, 'name' => 'PHP']], 'missing_skills' => [], 'explanation' => 'Test',
        ]);
        $evaluation = $this->persist(ApplicationAiEvaluation::class, [
            'application_id' => $application->id, 'job_id' => $jobs['job']->id, 'job_version_id' => $jobs['version']->id,
            'resume_snapshot_hash' => str_repeat('a', 64), 'model_version' => 'test', 'formula_version' => 'v1',
        ]);
        $application->load('candidate', 'job', 'jobVersion', 'statusHistories', 'notes', 'aiEvaluations');
        $this->assertTrue($application->candidate->is($resumes['candidate']));
        $this->assertTrue($application->job->is($jobs['job']));
        $this->assertTrue($application->jobVersion->is($jobs['version']));
        $this->assertTrue($application->statusHistories->first()->is($history));
        $this->assertTrue($application->notes->first()->is($note));
        $this->assertTrue($application->aiEvaluations->first()->is($evaluation));
        $this->assertTrue($resumes['resume']->applications->first()->is($application));
        $this->assertTrue($jobs['job']->applications->first()->is($application));
        $this->assertTrue($jobs['version']->applications->first()->is($application));
        $this->assertTrue($resumes['candidate']->applications->first()->is($application));
        $this->assertTrue($note->application->is($application));
        $this->assertTrue($note->recruiter->is($jobs['recruiter']));
        $this->assertTrue($history->actor->is($resumes['candidate']));
        $this->assertTrue($history->application->is($application));
        $this->assertSame(ApplicationStatus::Applied, $history->new_status);
        $this->assertNull($history->old_status);
        $this->assertFalse($history->usesTimestamps() && $history->getUpdatedAtColumn() !== null);
        $this->assertTrue($saved->candidate->is($resumes['candidate']));
        $this->assertTrue($saved->job->is($jobs['job']));
        $this->assertTrue($resumes['candidate']->savedJobs->first()->is($saved));
        $this->assertTrue($resumes['candidate']->recommendationRuns->first()->is($run));
        $this->assertTrue($run->load('items')->items->first()->is($item));
        $this->assertTrue($run->candidate->is($resumes['candidate']));
        $this->assertTrue($run->resume->is($resumes['resume']));
        $this->assertTrue($run->resumeRevisionQuery()->first()->is($resumes['first']));
        $this->assertTrue($run->resumeForCandidateQuery()->first()->is($resumes['resume']));
        $this->assertSame(RecommendationStatus::Pending, $run->status);
        $this->assertTrue($item->run->is($run));
        $this->assertTrue($item->job->is($jobs['job']));
        $this->assertTrue($item->jobVersion->is($jobs['version']));
        $this->assertSame('68.00', $item->score);
        $this->assertSame('PHP', $item->matched_skills[0]['name']);
        $this->assertSame([], $item->missing_skills);
        $this->assertTrue($evaluation->application->is($application));
        $this->assertTrue($evaluation->job->is($jobs['job']));
        $this->assertTrue($evaluation->jobVersion->is($jobs['version']));
        $this->assertSame(AiEvaluationStatus::Pending, $evaluation->evaluation_status);
        $this->assertNull($evaluation->score);
        $this->assertNull($evaluation->matched_skills);
    }

    public function test_composite_queries_reject_mismatched_unsaved_keys(): void
    {
        $first = $this->jobGraph();
        $second = $this->jobGraph();
        $resumes = $this->resumeGraph();
        $otherResumes = $this->resumeGraph();
        $application = $this->application($first, $resumes);
        $this->assertTrue($application->jobVersionQuery()->first()->is($first['version']));
        $application->job_id = $second['job']->id;
        $this->assertNull($application->jobVersionQuery()->first());
        $application->candidate_id = $otherResumes['candidate']->id;
        $this->assertNull($application->resumeForCandidateQuery()->first());
        $this->assertNull(JobVersion::forJob($second['job']->id)->find($first['version']->id));
        $this->assertNull(Application::forJob($second['job']->id)->find($application->id));
        $evaluation = new ApplicationAiEvaluation;
        $evaluation->forceFill(['application_id' => $application->id, 'job_id' => $second['job']->id, 'job_version_id' => $first['version']->id]);
        $this->assertNull($evaluation->applicationQuery()->first());
        $this->assertNull($evaluation->jobVersionQuery()->first());
        $item = new RecommendationItem;
        $item->forceFill(['job_id' => $second['job']->id, 'job_version_id' => $first['version']->id]);
        $this->assertNull($item->jobVersionQuery()->first());
        $log = new JobModerationLog;
        $log->forceFill(['job_id' => $second['job']->id, 'job_version_id' => $first['version']->id]);
        $this->assertNull($log->jobVersionQuery()->first());
        $second['job']->published_version_id = $first['version']->id;
        $this->assertNull($second['job']->publishedVersionQuery()->first());
        $this->assertNull((new Resume)->currentRevision()->first());
        $this->assertCount(0, (new ResumeRevision)->skills()->get());
    }

    public function test_publication_moderation_morphs_and_framework_notifications(): void
    {
        $graph = $this->jobGraph();
        $admin = $this->user(UserRole::Admin);
        $version = $graph['version'];
        $version->forceFill(['review_status' => JobReviewStatus::Approved, 'submitted_at' => now(), 'reviewed_at' => now()])->save();
        $job = $graph['job'];
        $job->forceFill(['published_version_id' => $version->id, 'published_at' => now(), 'expires_at' => now()->addDay(), 'status' => JobStatus::Open])->save();
        $log = $this->persist(JobModerationLog::class, ['job_id' => $job->id, 'job_version_id' => $version->id, 'admin_id' => $admin->id, 'action' => 'approve']);
        $this->assertTrue($job->load('publishedVersion', 'moderationLogs')->publishedVersion->is($version));
        $this->assertTrue($job->publishedVersionQuery()->first()->is($version));
        $this->assertTrue($job->moderationLogs->first()->is($log));
        $this->assertTrue($log->admin->is($admin));
        $this->assertTrue($log->job->is($job));
        $this->assertTrue($log->jobVersion->is($version));
        $this->assertTrue($version->moderationLog->is($log));
        foreach ([$graph['recruiter'], $graph['company'], $job] as $subject) {
            $alias = $subject->getMorphClass();
            $audit = new ActivityLog;
            $audit->forceFill([
                'actor_id' => $admin->id, 'action' => $alias === 'job' ? 'job.pause' : $alias.'.lock',
                'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'locked'], 'reason' => 'Test',
            ]);
            $subject->activityLogs()->save($audit);
            $this->assertSame($alias, $audit->subject_type);
            $this->assertTrue($audit->load('subject')->subject->is($subject));
            $this->assertTrue($audit->actor->is($admin));
            $this->assertSame('locked', $audit->new_values['status']);
        }
        $this->assertCount(3, $admin->actedActivityLogs);
        $this->assertCount(3, ActivityLog::with('subject')->get());
        $notification = $admin->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'TestNotification', 'data' => ['event_key' => 'test'],
        ]);
        $this->assertInstanceOf(DatabaseNotification::class, $notification);
        $this->assertSame('user', $notification->notifiable_type);
        $this->assertSame('test', $notification->data['event_key']);
        $this->assertTrue($notification->notifiable->is($admin));
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $notification->markAsRead();
        $this->assertSame(1, $admin->readNotifications()->count());
        $this->assertTrue(Hash::check('test-password', $admin->password));
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_enum_values_match_the_deployed_check_constraints(): void
    {
        $enums = [
            [UserRole::class, 'users', 'role'], [UserStatus::class, 'users', 'status'], [CompanyStatus::class, 'companies', 'status'],
            [JobStatus::class, 'jobs', 'status'], [JobReviewStatus::class, 'job_versions', 'review_status'],
            [ApplicationStatus::class, 'applications', 'status'], [ResumeParsingStatus::class, 'resume_revisions', 'parsing_status'],
            [AiEvaluationStatus::class, 'application_ai_evaluations', 'evaluation_status'], [RecommendationStatus::class, 'recommendation_runs', 'status'],
        ];
        foreach ($enums as [$enum, $table, $column]) {
            $definition = array_values((array) DB::selectOne("SHOW CREATE TABLE `$table`"))[1];
            $pattern = '/`'.preg_quote($column, '/').'` in \(([^)]+)\)/i';
            $this->assertSame(1, preg_match($pattern, $definition, $match));
            preg_match_all("/'([^']+)'/", $match[1], $values);
            $expected = $values[1];
            $actual = array_column($enum::cases(), 'value');
            sort($expected);
            sort($actual);
            $this->assertSame($expected, $actual, $enum);
        }
    }
}
