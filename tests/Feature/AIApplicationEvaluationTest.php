<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\AIEvaluateApplicationJob;
use App\Models\Application;
use App\Models\ApplicationAiEvaluation;
use App\Models\User;
use App\Services\AIApplicationEvaluationService;
use App\Services\AIOllamaService;
use App\Services\CandidateApplicationService;
use App\Services\CandidateResumeService;
use App\Services\RecruiterJobService;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class AIApplicationEvaluationTest extends AuthenticatedDemoTestCase
{
    use JobFixtures, PdfFixtures;

    private User $owner;

    private User $candidate;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        $manager = new DatabaseTransactionsManager([$connection->getName()]);
        $this->app->instance('db.transactions', $manager);
        $connection->setTransactionManager($manager);
        $manager->begin($connection->getName(), 1);
        Queue::fake();
        Storage::fake('local');
        config(['ai.embedding.enabled' => true, 'ai.embedding.cache_store' => 'array']);
        $this->fakeOllama();
        $this->candidate = auth()->user();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->owner = auth()->user();
        $this->companyFixture($this->owner->id);
        $job = app(RecruiterJobService::class)->create($this->owner, $this->completeInput());
        $version = $this->publishFixture($job);
        $version->forceFill([
            'ai_analysis_status' => 'completed', 'ai_analyzed_at' => now(), 'ai_model_version' => 'test/job',
            'ai_analysis_result' => array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []),
        ])->save();
        $resume = app(CandidateResumeService::class)->upload(
            $this->candidate, $this->pdf('PHP Laravel Git developer building backend applications')
        );
        $resume->currentRevision()->firstOrFail()->forceFill([
            'analysis_result' => $this->claims(), 'parsing_status' => 'completed', 'parsed_at' => now(),
            'extracted_text_path' => 'test.txt', 'parser_version' => 'test/cv',
        ])->save();
        $this->application = app(CandidateApplicationService::class)->apply($this->candidate, $job->id, [
            'resume' => $resume->public_id, 'job_version_id' => $job->published_version_id, 'attempt_no' => 1,
        ]);
        Queue::fake();
        $this->flushSession();
        $this->actingAs($this->owner);
    }

    private function claims(): array
    {
        $claims = array_fill_keys(array_keys(AIOllamaService::SECTIONS), []);
        $claims['technical_skills'] = [['text' => 'PHP', 'evidence' => 'PHP']];

        return $claims;
    }

    private function fakeOllama(bool $fail = false): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => [[
                'name' => 'embeddinggemma:300m', 'digest' => str_repeat('a', 64),
            ]]]),
            '127.0.0.1:11434/api/embed' => $fail ? Http::response([], 503) : Http::response([
                'model' => 'embeddinggemma:300m', 'embeddings' => [[1, 0], [0.8, 0.6]],
            ]),
            '127.0.0.1:11434/api/chat' => Http::response([
                'done' => true, 'message' => ['content' => json_encode($this->claims())],
            ]),
        ]);
    }

    private function requestEvaluation(): ApplicationAiEvaluation
    {
        return app(AIApplicationEvaluationService::class)->request($this->owner, $this->application->public_id);
    }

    private function process(ApplicationAiEvaluation $evaluation): string
    {
        return app(AIApplicationEvaluationService::class)->process(
            $evaluation->id, $evaluation->processing_token, $this->owner->id
        );
    }

    public function test_candidate_and_recruiter_send_identical_embedding_text_for_same_sources(): void
    {
        $revision = $this->application->resumeRevisionQuery()->firstOrFail();
        $analysis = $this->claims();
        $analysis['technical_skills'] = [['text' => 'PHP Laravel Git', 'evidence' => 'PHP Laravel Git']];
        $revision->forceFill(['analysis_result' => $analysis])->save();
        foreach ([101, 100, 2] as $skillId) {
            DB::table('resume_skills')->insert([
                'resume_id' => $revision->resume_id, 'resume_revision' => $revision->revision,
                'skill_id' => $skillId, 'source' => 'model', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $candidate = app(\App\Services\AIMatchingService::class)
            ->forCandidate($this->candidate, $this->application->job_id);
        $evaluation = $this->requestEvaluation();
        $this->assertSame('completed', $this->process($evaluation));
        $requests = Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->values();
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0][0]['input'], $requests[1][0]['input']);
        $this->assertSame($candidate['score'], (float) $evaluation->fresh()->score);
        $this->assertSame(round($candidate['semantic_score'], 2), (float) $evaluation->fresh()->semantic_score);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
    }

    public function test_matching_is_saved_and_displayed_without_changing_application(): void
    {
        $this->post(route('recruiter.application-evaluate', $this->application->public_id))->assertRedirect();
        $evaluation = $this->application->aiEvaluations()->firstOrFail();
        Queue::assertPushed(AIEvaluateApplicationJob::class, 1);
        $this->assertSame('completed', $this->process($evaluation));
        $evaluation->refresh();
        $this->assertSame('88.00', $evaluation->score);
        $this->assertSame('80.00', $evaluation->semantic_score);
        $this->assertSame('100.00', $evaluation->skill_score);
        $this->assertSame(100, $evaluation->matched_skills[0]['skill_id']);
        $this->assertSame($this->application->job_version_id, $evaluation->job_version_id);
        $this->assertNotEmpty($evaluation->explanation);
        $this->assertSame('applied', $this->application->fresh()->status->value);
        $this->get(route('recruiter.application-detail', ['id' => $this->application->public_id]))
            ->assertOk()->assertSee('88,00/100')->assertSee('không phải xác suất trúng tuyển');
        $this->get(route('recruiter.applications'))->assertOk()->assertSee('88,00/100');
        $this->get(route('recruiter.applications', ['sort' => 'score', 'ai_status' => 'completed']))
            ->assertOk()->assertSee('88,00/100');
        $this->get(route('recruiter.applications', ['ai_status' => 'not_evaluated']))
            ->assertOk()->assertDontSee('88,00/100');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
    }

    public function test_snapshot_fallback_preserves_historical_revision_and_job_version(): void
    {
        $revision = $this->application->resumeRevisionQuery()->firstOrFail();
        $revision->forceFill(['analysis_result' => null, 'parsing_status' => 'pending'])->save();
        $original = $revision->getAttributes();
        $resume = $this->application->resume;
        app(CandidateResumeService::class)->upload($this->candidate, $this->pdf('Java unrelated new CV'), $resume->public_id);
        $newVersion = $this->application->jobVersion->replicate();
        $newVersion->forceFill(['version_number' => 2])->save();
        $job = $this->application->job;
        // Tin hiện đã đóng vẫn phải đánh giá được phiên bản lúc ứng tuyển.
        $job->forceFill(['status' => 'closed', 'published_version_id' => $newVersion->id])->save();
        $evaluation = $this->requestEvaluation();
        $this->assertSame('completed', $this->process($evaluation));
        $this->assertSame('100.00', $evaluation->fresh()->skill_score);
        $this->assertNotSame($newVersion->id, $evaluation->fresh()->job_version_id);
        $this->assertSame($original, $revision->fresh()->getAttributes());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
        $this->assertSame($this->application->resume_snapshot_hash,
            hash_file('sha256', Storage::disk('local')->path($this->application->resume_snapshot_path)));
    }

    public function test_duplicate_requests_retry_and_stale_tokens_are_safe(): void
    {
        $evaluation = $this->requestEvaluation();
        $this->travel(2)->minutes();
        $this->assertSame($evaluation->id, $this->requestEvaluation()->id);
        Queue::assertPushed(AIEvaluateApplicationJob::class, 1);
        $this->assertSame('obsolete', app(AIApplicationEvaluationService::class)
            ->process($evaluation->id, 'old-token', $this->owner->id));
        $this->fakeOllama(true);
        try {
            $this->process($evaluation);
            $this->fail('Expected retryable embedding failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Application AI evaluation temporarily unavailable.', $exception->getMessage());
        }
        $this->assertSame('pending', $evaluation->fresh()->evaluation_status->value);
        $this->fakeOllama();
        $this->assertSame('completed', $this->process($evaluation));
        $this->assertSame(2, $evaluation->fresh()->attempt_count);
        config(['ai.embedding.preprocessing_version' => 'changed-for-test']);
        $this->assertSame('stale', app(AIApplicationEvaluationService::class)
            ->summaries($this->owner, [$this->application])[$this->application->id]['state']);
        $new = $this->requestEvaluation();
        $this->assertNotSame($evaluation->id, $new->id);
        config(['ai.embedding.preprocessing_version' => 'changed-again']);
        $this->assertSame('failed', $this->process($new));
        $this->assertSame('stale_inputs', $new->fresh()->error_code);
    }

    public function test_only_owner_can_request_or_read_private_evaluation(): void
    {
        $url = route('recruiter.application-evaluate', $this->application->public_id);
        $this->loginDemoUser(UserRole::Recruiter);
        $this->flushSession();
        $this->post($url)->assertNotFound();
        foreach ([UserRole::Candidate, UserRole::Admin] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            $this->post($url)->assertForbidden();
        }
        $this->assertSame(0, ApplicationAiEvaluation::count());
        $this->flushSession();
        $this->actingAs($this->owner);
        $evaluation = $this->requestEvaluation();
        $this->owner->forceFill(['status' => 'locked'])->save();
        $this->assertSame('failed', $this->process($evaluation));
        $this->assertNull($evaluation->fresh()->score);
    }

    public function test_bad_snapshot_or_missing_analysis_never_creates_fake_scores(): void
    {
        $evaluation = $this->requestEvaluation();
        Storage::disk('local')->put($this->application->resume_snapshot_path, 'not a PDF');
        $this->assertSame('failed', $this->process($evaluation));
        $this->assertSame('invalid_application_snapshot', $evaluation->fresh()->error_code);
        $this->assertNull($evaluation->fresh()->score);
        $this->assertSame('applied', $this->application->fresh()->status->value);
        $this->get(route('recruiter.application-detail', ['id' => $this->application->public_id]))
            ->assertOk()->assertSee('Đánh giá thất bại')->assertDontSee('0,00/100');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/embed'));
        $this->application->jobVersion->forceFill(['ai_analysis_result' => null, 'ai_analysis_status' => 'pending'])->save();
        $missing = $this->requestEvaluation();
        $this->assertSame('failed', $this->process($missing));
        $this->assertSame('historical_job_analysis_missing', $missing->fresh()->error_code);
        $this->assertNull($missing->fresh()->score);
    }
}
