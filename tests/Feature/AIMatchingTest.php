<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ResumeSkill;
use App\Services\AIMatchingService;
use App\Services\AISemanticSimilarityService;
use App\Services\AIOllamaService;
use App\Services\CandidateResumeService;
use App\Services\RecruiterJobService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;
use Tests\Support\PdfFixtures;

class AIMatchingTest extends AuthenticatedDemoTestCase
{
    public function test_embedding_preparation_canonicalizes_skills_but_preserves_source_differences(): void
    {
        $matching = app(AIMatchingService::class);
        $resume = ['technical_skills' => [['text' => 'PHP Laravel Git', 'evidence' => 'PHP Laravel Git']]];
        $job = ['responsibilities' => [['text' => 'Build backend APIs', 'evidence' => 'Build backend APIs']]];
        $first = $matching->embeddingTexts($resume, $job, [101, 2, 100, 2], [100, 2]);
        $second = $matching->embeddingTexts($resume, $job, [100, 101, 2], [2, 100]);
        $this->assertSame($first, $second);
        $this->assertStringStartsWith("Git\nPHP\nLaravel\n", $first['resume']);
        $changed = $resume;
        $changed['technical_skills'][0] = ['text' => 'PHP', 'evidence' => 'PHP'];
        $this->assertNotSame($first, $matching->embeddingTexts($changed, $job, [100], [100, 2]));
        $this->assertSame('PHP Laravel Git', $resume['technical_skills'][0]['text']);
    }

    use JobFixtures, PdfFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        config(['ai.embedding.enabled' => false]);
    }

    private function matchingFixture(): array
    {
        $candidate = auth()->user();
        $this->loginDemoUser(UserRole::Recruiter);
        $owner = auth()->user();
        $this->companyFixture($owner->id);
        $job = app(RecruiterJobService::class)->create($owner, $this->completeInput());
        $version = $this->publishFixture($job);
        $version->forceFill([
            'ai_analysis_status' => 'completed',
            'ai_analysis_result' => array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []),
            'ai_model_version' => 'test/job-schema-1', 'ai_analyzed_at' => now(),
        ])->save();
        $this->actingAs($candidate);
        $resume = app(CandidateResumeService::class)->upload($candidate, $this->pdf('PHP developer'));
        $revision = $resume->currentRevision()->firstOrFail();
        $analysis = array_fill_keys(array_keys(AIOllamaService::SECTIONS), []);
        $analysis['technical_skills'] = [['text' => 'PHP', 'evidence' => 'PHP']];
        $revision->forceFill([
            'analysis_result' => $analysis, 'parsing_status' => 'completed',
            'parsed_at' => now(), 'extracted_text_path' => 'test/private.txt', 'parser_version' => 'test/schema-1',
        ])->save();
        (new ResumeSkill)->forceFill([
            'resume_id' => $resume->id, 'resume_revision' => 1, 'skill_id' => 100, 'source' => 'model',
        ])->save();

        return [$candidate, $job, $version, $resume, $revision, $owner];
    }

    public function test_skills_formula_rounding_and_no_duplicates(): void
    {
        $service = app(AIMatchingService::class);
        $skills = $service->matchSkills([100, 100], [100, 101, 100]);
        $this->assertEquals(50, $skills['skill_score']);
        $this->assertCount(1, $skills['matched_skills']);
        $this->assertSame(101, $skills['missing_skills'][0]['skill_id']);
        $this->assertSame(68.0, $service->totalScore(80, $skills['skill_score']));
        $third = $service->matchSkills([100], [100, 101, 102]);
        $this->assertSame(61.33, $service->totalScore(80, $third['skill_score']));
        $this->assertSame(48.0, $service->totalScore(80, $service->matchSkills([], [])['skill_score']));
        $this->assertNull($service->matchSkills([999999], [100])['skill_score']);
        $this->expectException(\InvalidArgumentException::class);
        $service->totalScore(101, 0);
    }

    public function test_missing_semantic_never_produces_total_or_database_results(): void
    {
        [$candidate, $job, $version, $resume, $revision] = $this->matchingFixture();
        $service = app(AIMatchingService::class);
        $result = $service->forCandidate($candidate, $job->id);
        $this->assertSame('unavailable', $result['status']);
        $this->assertEquals(100, $result['skill_score']);
        $this->assertNull($result['score']);
        $this->assertNull($result['semantic_score']);
        $this->assertNull($result['model_version']);
        $this->assertSame('v1', $result['formula_version']);
        $this->assertSame($revision->id, $result['resume_revision_id']);
        $this->assertSame($version->id, $result['job_version_id']);
        $this->assertSame($result, $service->forCandidate($candidate, $job->id));
        $this->assertFalse($service->isStale($candidate, $result));
        foreach (['recommendation_runs', 'recommendation_items', 'application_ai_evaluations'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        Http::assertNothingSent();
    }

    public function test_unfinished_or_malformed_analysis_has_no_skill_score(): void
    {
        [$candidate, $job, $version, $resume, $revision] = $this->matchingFixture();
        $service = app(AIMatchingService::class);
        $revision->forceFill(['parsing_status' => 'failed'])->save();
        $result = $service->forCandidate($candidate, $job->id);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNull($result['skill_score']);
        $original = $revision->analysis_result;
        $revision->forceFill([
            'parsing_status' => 'completed',
            'analysis_result' => array_fill_keys(array_keys(AIOllamaService::SECTIONS), []),
        ])->save();
        $this->assertContains('resume_evidence', $service->forCandidate($candidate, $job->id)['missing_data']);
        $revision->forceFill(['analysis_result' => $original])->save();
        $revision->forceFill(['parsing_status' => 'completed'])->save();
        $version->forceFill(['ai_analysis_result' => ['required_skills' => 'invalid']])->save();
        $result = $service->forCandidate($candidate, $job->id);
        $this->assertContains('completed_job_analysis', $result['missing_data']);
        $this->assertNull($result['score']);
        app(CandidateResumeService::class)->change($candidate, $resume->public_id, 'delete', []);
        $this->assertContains('primary_resume_revision', $service->forCandidate($candidate, $job->id)['missing_data']);
    }

    public function test_versions_primary_cv_and_revision_invalidate_old_results(): void
    {
        [$candidate, $job, $version, $resume] = $this->matchingFixture();
        $service = app(AIMatchingService::class);
        $result = $service->forCandidate($candidate, $job->id);
        $second = $version->replicate();
        $second->forceFill(['version_number' => 2, 'review_status' => 'pending', 'reviewed_at' => null])->save();
        $this->assertFalse($service->isStale($candidate, $result));
        $second->forceFill(['review_status' => 'rejected', 'reviewed_at' => now()])->save();
        $this->assertFalse($service->isStale($candidate, $result));
        $second->forceFill(['review_status' => 'approved'])->save();
        $job->forceFill(['published_version_id' => $second->id])->save();
        $this->assertTrue($service->isStale($candidate, $result));
        $result = $service->forCandidate($candidate, $job->id);
        $cvService = app(CandidateResumeService::class);
        $other = $cvService->upload($candidate, $this->pdf('Other CV'));
        $cvService->change($candidate, $other->public_id, 'main', []);
        $this->assertTrue($service->isStale($candidate, $result));
        $cvService->change($candidate, $resume->public_id, 'main', []);
        $result = $service->forCandidate($candidate, $job->id);
        $cvService->upload($candidate, $this->pdf('New revision'), $resume->public_id);
        $this->assertTrue($service->isStale($candidate, $result));
    }

    public function test_ownership_role_active_and_public_visibility(): void
    {
        [$candidate, $job, $version, $resume, $revision, $owner] = $this->matchingFixture();
        $service = app(AIMatchingService::class);
        $result = $service->forCandidate($candidate, $job->id);
        foreach (['paused', 'closed', 'blocked'] as $status) {
            $job->forceFill([
                'status' => $status, 'status_before_block' => $status === 'blocked' ? 'closed' : null,
            ])->save();
            $hidden = $service->forCandidate($candidate, $job->id);
            $this->assertSame(['public_job'], $hidden['missing_data']);
            $this->assertNull($hidden['job_version_id']);
            $this->assertTrue($service->isStale($candidate, $result));
        }
        $job->forceFill(['status' => 'open', 'status_before_block' => null, 'expires_at' => now()->subDay()])->save();
        $this->assertSame('unavailable', $service->forCandidate($candidate, $job->id)['status']);
        $job->forceFill(['expires_at' => now()->addDay()])->save();
        $owner->forceFill(['status' => 'locked'])->save();
        $this->assertSame('unavailable', $service->forCandidate($candidate, $job->id)['status']);
        $owner->forceFill(['status' => 'active'])->save();
        $job->company->forceFill(['status' => 'locked'])->save();
        $this->assertSame('unavailable', $service->forCandidate($candidate, $job->id)['status']);
        $job->company->forceFill(['status' => 'active'])->save();
        $this->loginDemoUser(UserRole::Candidate);
        $other = auth()->user();
        $this->assertNull($service->forCandidate($other, $job->id)['resume_id']);
        foreach ([$owner, $candidate->forceFill(['status' => 'locked'])] as $invalidActor) {
            $invalidActor->save();
            try {
                $service->forCandidate($invalidActor, $job->id);
                $this->fail('Actor không hợp lệ phải bị từ chối.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(AuthorizationException::class);
        $service->isStale($other, $result);
    }

    private function fakeEmbeddings(mixed $embeddingResponse = null, string $digest = 'a'): void
    {
        config(['ai.embedding.enabled' => true, 'ai.embedding.cache_store' => 'array']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => [[
                'name' => 'embeddinggemma:300m', 'digest' => str_repeat($digest, 64),
            ]]]),
            '127.0.0.1:11434/api/embed' => $embeddingResponse ?? Http::response([
                'model' => 'embeddinggemma:300m', 'embeddings' => [[1, 0], [0.8, 0.6]],
            ]),
        ]);
    }

    public function test_semantic_vector_normalization_and_cosine_reject_invalid_values(): void
    {
        $semantic = app(AISemanticSimilarityService::class);
        $this->assertEqualsWithDelta(0.8, $semantic->cosine([10, 0], [8, 6]), 0.000001);
        $this->assertEqualsWithDelta(-1, $semantic->cosine([1, 0], [-1, 0]), 0.000001);
        $this->assertEqualsWithDelta(0, $semantic->cosine([1, 0], [0, 1]), 0.000001);
        foreach ([[0, 0], [INF, 1], [NAN, 1], ['1', 2], [1], ['x' => 1, 'y' => 2]] as $vector) {
            try {
                $semantic->normalizeVector($vector);
                $this->fail('Vector không hợp lệ phải bị từ chối.');
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(\RuntimeException::class);
        $semantic->cosine([1, 2], [1, 2, 3]);
    }

    public function test_semantic_completed_formula_and_cache_version_keys(): void
    {
        [$candidate, $job] = $this->matchingFixture();
        $this->fakeEmbeddings();
        $service = app(AIMatchingService::class);
        $result = $service->forCandidate($candidate, $job->id);
        $this->assertSame('completed', $result['status']);
        $this->assertEqualsWithDelta(80, $result['semantic_score'], 0.000001);
        $this->assertSame(88.0, $result['score']);
        $this->assertSame($result, $service->forCandidate($candidate, $job->id));
        $this->assertSame(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->count());
        config(['ai.embedding.preprocessing_version' => 'career-test-2']);
        $this->assertTrue($service->isStale($candidate, $result));
        $this->assertSame(2, Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->count());
        $this->fakeEmbeddings(null, 'b');
        $changed = $service->forCandidate($candidate, $job->id);
        $this->assertStringContainsString(str_repeat('b', 64), $changed['model_version']);
    }

    public function test_semantic_unavailable_or_missing_input_never_completes(): void
    {
        [$candidate, $job, $version] = $this->matchingFixture();
        $this->fakeEmbeddings(Http::response(['error' => 'private failure'], 500));
        $result = app(AIMatchingService::class)->forCandidate($candidate, $job->id);
        $this->assertSame('unavailable', $result['status']);
        $this->assertNull($result['score']);
        $this->assertSame('embedding_request_failed', $result['error_code']);
        $this->assertStringNotContainsString('private failure', json_encode($result));
        $this->fakeEmbeddings(Http::response([
            'model' => 'embeddinggemma:300m', 'embeddings' => [[0, 0], [1, 0]],
        ]));
        $this->assertNull(app(AIMatchingService::class)->forCandidate($candidate, $job->id)['score']);
        $version->skillRequirements()->delete();
        $result = app(AIMatchingService::class)->forCandidate($candidate, $job->id);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame('embedding_input_missing', $result['error_code']);
    }

    public function test_semantic_rechecks_inputs_and_permission_after_http(): void
    {
        [$candidate, $job, $version, $resume] = $this->matchingFixture();
        $this->fakeEmbeddings(function () use ($candidate, $resume) {
            app(CandidateResumeService::class)->upload($candidate, $this->pdf('Updated CV'), $resume->public_id);

            return Http::response(['model' => 'embeddinggemma:300m', 'embeddings' => [[1, 0], [1, 0]]]);
        });
        $result = app(AIMatchingService::class)->forCandidate($candidate, $job->id);
        $this->assertContains('stale_inputs', $result['missing_data']);
        $this->assertNull($result['score']);
        $this->loginDemoUser(UserRole::Recruiter);
        $this->expectException(AuthorizationException::class);
        app(AIMatchingService::class)->forCandidate(auth()->user(), $job->id);
    }

    public function test_semantic_preprocessing_and_long_input_are_bounded(): void
    {
        $this->fakeEmbeddings();
        $semantic = app(AISemanticSimilarityService::class);
        $analysis = [
            'technical_skills' => [['text' => 'PHP', 'evidence' => 'PHP']],
            'work_experience' => [['text' => 'Email person@example.com', 'evidence' => 'Email person@example.com']],
            'education' => [['text' => 'Private school', 'evidence' => 'Private school']],
        ];
        $this->assertSame('PHP', $semantic->prepareText($analysis, 'resume', ['PHP']));
        $this->fakeEmbeddings(function ($request) {
            $this->assertFalse($request['truncate']);
            $this->assertCount(12, $request['input']);
            foreach ($request['input'] as $chunk) {
                $this->assertLessThanOrEqual(1000, mb_strlen($chunk));
            }

            return Http::response([
                'model' => 'embeddinggemma:300m', 'embeddings' => array_fill(0, 12, [1, 0]),
            ]);
        });
        $first = $semantic->compare(str_repeat('PHP ', 2000), str_repeat('SQL ', 2000), [1, 2]);
        $this->assertEquals(100, $first['semantic_score']);
        $second = $semantic->compare(str_repeat('PHP ', 2000), str_repeat('SQL ', 2000), [1, 2]);
        $this->assertTrue($second['cache_hit']);
        $semantic->compare(str_repeat('PHP ', 2000), str_repeat('SQL ', 2000), [1, 3]);
        $this->assertSame(2, Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/embed'))->count());
    }
}
