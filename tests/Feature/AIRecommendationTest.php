<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\AIRecommendJobsJob;
use App\Models\RecommendationRun;
use App\Models\ResumeSkill;
use App\Services\AIRecommendationService;
use App\Services\AIOllamaService;
use App\Services\CandidateResumeService;
use App\Services\RecruiterJobService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class AIRecommendationTest extends AuthenticatedDemoTestCase
{
    use JobFixtures, PdfFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config(['ai.embedding.enabled' => true, 'ai.embedding.cache_store' => 'array']);
        $this->fakeOllama();
    }

    private function fakeOllama(bool $fail = false): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => [[
                'name' => 'embeddinggemma:300m', 'digest' => str_repeat('a', 64),
            ]]]),
            '127.0.0.1:11434/api/embed' => $fail ? Http::response([], 503) : Http::response([
                'model' => 'embeddinggemma:300m', 'embeddings' => [[1, 0], [0.8, 0.6]],
            ]),
        ]);
    }

    private function fixtures(int $count = 2): array
    {
        $candidate = auth()->user();
        $this->loginDemoUser(UserRole::Recruiter);
        $owner = auth()->user();
        $this->companyFixture($owner->id);
        $jobs = [];
        for ($index = 0; $index < $count; $index++) {
            $input = $this->completeInput();
            $input['title'] = 'Recommendation role '.$index;
            $input['skills'] = $index === 0 ? [100] : [100, 101];
            $job = app(RecruiterJobService::class)->create($owner, $input);
            $version = $this->publishFixture($job);
            $version->forceFill([
                'ai_analysis_status' => 'completed', 'ai_analyzed_at' => now(), 'ai_model_version' => 'test/job',
                'ai_analysis_result' => array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []),
            ])->save();
            $jobs[] = $job;
        }
        $this->actingAs($candidate);
        $resume = app(CandidateResumeService::class)->upload($candidate, $this->pdf('PHP developer'));
        $revision = $resume->currentRevision()->firstOrFail();
        $analysis = array_fill_keys(array_keys(AIOllamaService::SECTIONS), []);
        $analysis['technical_skills'] = [['text' => 'PHP', 'evidence' => 'PHP']];
        $revision->forceFill([
            'analysis_result' => $analysis, 'parsing_status' => 'completed', 'parsed_at' => now(),
            'extracted_text_path' => 'test.txt', 'parser_version' => 'test/cv',
        ])->save();
        (new ResumeSkill)->forceFill([
            'resume_id' => $resume->id, 'resume_revision' => 1, 'skill_id' => 100, 'source' => 'model',
        ])->save();

        return [$candidate, $jobs, $resume, $revision, $owner];
    }

    private function finish(RecommendationRun $run, array $targets): void
    {
        foreach ($targets ?: [null] as $index => $target) {
            $run->refresh();
            app(AIRecommendationService::class)->process($run->id, $run->processing_token, $targets, $index);
        }
    }

    private function targets(array $jobs): array
    {
        return collect($jobs)->sortByDesc('id')->map(fn ($job) => [$job->id, $job->published_version_id])->values()->all();
    }

    public function test_candidate_queue_ranking_and_existing_view(): void
    {
        [$candidate, $jobs] = $this->fixtures();
        $service = app(AIRecommendationService::class);
        $page = $service->page($candidate);
        $this->assertSame('pending', $page['recommendationState']);
        $this->assertSame(0, Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->count());
        $run = RecommendationRun::firstOrFail();
        Queue::assertPushed(AIRecommendJobsJob::class, fn ($job) => $job->afterCommit && $job->runId === $run->id);
        $queued = new AIRecommendJobsJob($run->id, $run->processing_token, $this->targets($jobs), 0);
        $this->assertTrue($queued->afterCommit);
        $this->assertSame('ai', $queued->queue);
        $this->finish($run, $this->targets($jobs));
        $page = $service->page($candidate);
        $this->assertSame('completed', $page['recommendationState']);
        $this->assertSame([$jobs[0]->id, $jobs[1]->id], $page['recommendations']->pluck('job.id')->all());
        $this->assertSame(['88.00', '68.00'], $page['recommendations']->pluck('score')->all());
        $this->get(route('candidate.recommendations'))->assertOk()->assertSee('88.00/100')->assertDontSee('minh họa');
    }

    public function test_duplicate_retry_token_and_partial_results_are_safe(): void
    {
        [$candidate, $jobs] = $this->fixtures();
        $service = app(AIRecommendationService::class);
        $service->page($candidate);
        $service->page($candidate, [], true);
        $this->assertSame(1, RecommendationRun::count());
        $run = RecommendationRun::firstOrFail();
        $token = $run->processing_token;
        $targets = $this->targets($jobs);
        $service->process($run->id, $token, $targets, 0);
        $this->assertSame('obsolete', $service->process($run->id, $token, $targets, 0));
        $this->assertSame(1, $run->items()->count());
        $this->assertCount(0, $service->page($candidate)['recommendations']);
        $this->fakeOllama(true);
        $run->refresh();
        try {
            $service->process($run->id, $run->processing_token, $targets, 1);
            $this->fail('Lỗi embedding phải được retry.');
        } catch (\RuntimeException) {
            $this->assertNull($run->refresh()->lease_expires_at);
        }
        $this->fakeOllama();
        $service->process($run->id, $run->processing_token, $targets, 1);
        $this->assertSame('completed', $run->refresh()->status->value);
        $this->assertSame(2, $run->items()->count());
        $service->process($run->id, $run->refresh()->processing_token, $targets, 1);
        $this->assertSame(2, $run->items()->count());
        $this->assertSame(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->count());
    }

    public function test_changed_versions_catalog_and_cv_never_serve_old_points(): void
    {
        [$candidate, $jobs, $resume] = $this->fixtures(1);
        $service = app(AIRecommendationService::class);
        $service->page($candidate);
        $old = RecommendationRun::firstOrFail();
        $this->finish($old, $this->targets($jobs));
        $version = $jobs[0]->publishedVersion;
        $second = $version->replicate();
        $second->forceFill(['version_number' => 2, 'review_status' => 'pending', 'reviewed_at' => null])->save();
        $this->assertSame('completed', $service->page($candidate)['recommendationState']);
        $second->forceFill(['review_status' => 'approved', 'reviewed_at' => now()])->save();
        $jobs[0]->forceFill(['published_version_id' => $second->id])->save();
        $this->assertSame('pending', $service->page($candidate)['recommendationState']);
        $this->assertNotNull($old->refresh()->stale_at);
        config(['job-catalog.skills.100' => 'PHP revised']);
        $service->page($candidate);
        $this->assertSame(3, RecommendationRun::count());
        $latest = RecommendationRun::latest('id')->first();
        $newJob = app(RecruiterJobService::class)->create($jobs[0]->company->recruiter, $this->completeInput());
        $this->publishFixture($newJob);
        $service->page($candidate);
        $this->assertSame(4, RecommendationRun::count());
        $this->assertSame('obsolete', $service->process($latest->id, $latest->processing_token, $this->targets($jobs), 0));
        app(CandidateResumeService::class)->upload($candidate, $this->pdf('Updated'), $resume->public_id);
        $this->assertSame('cv_pending', $service->page($candidate)['recommendationState']);
        $this->assertCount(0, $service->page($candidate)['recommendations']);
    }

    public function test_public_visibility_and_other_candidate_permissions(): void
    {
        [$candidate, $jobs] = $this->fixtures(1);
        $service = app(AIRecommendationService::class);
        $service->page($candidate);
        $run = RecommendationRun::firstOrFail();
        $this->finish($run, $this->targets($jobs));
        $jobs[0]->forceFill(['status' => 'closed'])->save();
        $this->assertCount(0, $service->page($candidate)['recommendations']);
        foreach (['paused', 'blocked'] as $status) {
            $jobs[0]->forceFill(['status' => $status, 'status_before_block' => $status === 'blocked' ? 'closed' : null])->save();
            $this->assertCount(0, $service->page($candidate)['recommendations']);
        }
        $jobs[0]->forceFill(['status' => 'open', 'status_before_block' => null, 'expires_at' => now()->subDay()])->save();
        $this->assertCount(0, $service->page($candidate)['recommendations']);
        $this->loginDemoUser(UserRole::Candidate);
        $this->assertSame('no_resume', $service->page(auth()->user())['recommendationState']);
        $this->get(route('candidate.recommendations'))->assertOk()->assertDontSee('88.00/100');
        $this->flushSession();
        $this->loginDemoUser(UserRole::Recruiter);
        $this->getJson(route('candidate.recommendations'))->assertForbidden();
        $this->postJson(route('candidate.recommendations.refresh'))->assertForbidden();
    }

    public function test_empty_failed_retry_and_cv_states(): void
    {
        $this->get(route('candidate.recommendations'))->assertOk()->assertSee('Hãy tải CV');
        [$candidate, $jobs, $resume, $revision] = $this->fixtures(1);
        $service = app(AIRecommendationService::class);
        $jobs[0]->publishedVersion->forceFill(['ai_analysis_status' => 'pending'])->save();
        $service->page($candidate);
        $run = RecommendationRun::firstOrFail();
        $this->finish($run, $this->targets($jobs));
        $this->assertSame('completed', $run->refresh()->status->value);
        $this->assertSame(0, $run->items()->count());
        $this->get(route('candidate.recommendations'))->assertOk()->assertSee('Chưa có tin đủ dữ liệu');
        config(['job-catalog.skills.100' => 'PHP changed']);
        $service->page($candidate);
        $failed = RecommendationRun::latest('id')->first();
        $token = $failed->processing_token;
        $service->failRun($failed->id, $token);
        $this->assertSame('failed', $service->page($candidate)['recommendationState']);
        $this->travel(61)->seconds();
        $service->page($candidate, [], true);
        $this->assertSame('pending', $failed->refresh()->status->value);
        $this->assertNotSame($token, $failed->processing_token);
        $service->failRun($failed->id, $token);
        $this->assertSame('pending', $failed->refresh()->status->value);
        $revision->forceFill(['parsing_status' => 'failed'])->save();
        $this->assertSame('cv_failed', $service->page($candidate)['recommendationState']);
    }

    public function test_embedding_batch_context_failure_and_terminal_error_are_not_hidden(): void
    {
        [$candidate, $jobs, $resume, $revision] = $this->fixtures(1);
        $analysis = $revision->analysis_result;
        $analysis['work_experience'] = [[
            'text' => str_repeat('Phát triển hệ thống PHP Laravel. ', 24),
            'evidence' => str_repeat('Phát triển hệ thống PHP Laravel. ', 24),
        ]];
        $revision->forceFill(['analysis_result' => $analysis])->save();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => [[
                'name' => 'embeddinggemma:300m', 'digest' => str_repeat('a', 64),
            ]]]),
            '127.0.0.1:11434/api/embed' => function ($request) {
                $this->assertGreaterThan(128, mb_strlen($request['input'][0]));
                $this->assertSame(2048, $request['options']['num_batch']);
                $this->assertFalse($request['truncate']);

                return Http::response([
                    'model' => 'embeddinggemma:300m',
                    'embeddings' => array_fill(0, count($request['input']), [1, 0]),
                ]);
            },
        ]);
        $service = app(AIRecommendationService::class);
        $service->page($candidate);
        $run = RecommendationRun::firstOrFail();
        $this->finish($run, $this->targets($jobs));
        $this->assertSame('completed', $run->refresh()->status->value);
        $this->assertSame(1, $run->items()->count());

        config(['ai.embedding.preprocessing_version' => 'failure-test']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => [[
                'name' => 'embeddinggemma:300m', 'digest' => str_repeat('a', 64),
            ]]]),
            '127.0.0.1:11434/api/embed' => Http::response(['error' => 'the input length exceeds the context length'], 413),
        ]);
        $service->page($candidate);
        $failed = RecommendationRun::latest('id')->firstOrFail();
        $this->assertSame('failed', $service->process($failed->id, $failed->processing_token, $this->targets($jobs), 0));
        $service->failRun($failed->id, $failed->processing_token);
        $this->assertSame('embedding_input_too_long', $failed->refresh()->error_code);
        $this->assertSame(0, $failed->items()->count());
    }
}
