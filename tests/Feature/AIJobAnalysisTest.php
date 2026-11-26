<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\AIAnalyzeJobJob;
use App\Models\JobVersion;
use App\Models\User;
use App\Services\AIJobAnalysisService;
use App\Services\AIOllamaService;
use App\Services\JobModerationService;
use App\Services\RecruiterJobService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class AIJobAnalysisTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Recruiter;

    private User $owner;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = auth()->user();
        $this->companyFixture($this->owner->id);
        $this->input = $this->completeInput();
        Http::preventStrayRequests();
        Queue::fake();
        config(['ai.base_url' => 'http://127.0.0.1:11434']);
    }

    private function version(): JobVersion
    {
        $job = app(RecruiterJobService::class)->create($this->owner, $this->input);

        return $this->publishFixture($job);
    }

    private function response(JobVersion $version): array
    {
        $result = array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []);
        $name = $version->skills->firstOrFail()->name;
        $result['preferred_skills'] = [['text' => $name, 'evidence' => $name]];

        return ['done' => true, 'message' => ['content' => json_encode($result)]];
    }

    private function process(JobVersion $version): void
    {
        $version->refresh();
        app(AIJobAnalysisService::class)->process($version->id, $version->ai_processing_token);
    }

    public function test_approval_enqueues_once_after_commit(): void
    {
        $jobs = app(RecruiterJobService::class);
        $job = $jobs->create($this->owner, $this->input);
        $version = $jobs->latest($job);
        $jobs->submit($this->owner, $job->id, [
            'version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at'),
        ]);
        $this->loginDemoUser(UserRole::Admin);
        $version->refresh();
        app(JobModerationService::class)->decide(auth()->user(), $version->id, 'approve', [
            'updated_at' => $version->getRawOriginal('updated_at'),
            'job_updated_at' => $job->fresh()->getRawOriginal('updated_at'),
        ]);
        Queue::assertNothingPushed();
        DB::commit(); // Lớp test cha chỉ cho chạy trên database test riêng.
        app(AIJobAnalysisService::class)->enqueue($version->id);
        Queue::assertPushed(AIAnalyzeJobJob::class, 1);
        Queue::assertPushed(AIAnalyzeJobJob::class, fn ($job) => $job->versionId === $version->id
            && $job->connection === 'ai' && $job->afterCommit);
        Http::assertNothingSent();
        // Dọn fixture đã commit để các regression đếm tổng không nhận dữ liệu của test này.
        DB::table('job_moderation_logs')->where('job_id', $job->id)->delete();
        DB::table('jobs')->where('id', $job->id)->update([
            'published_version_id' => null, 'status' => 'draft', 'published_at' => null, 'expires_at' => null,
        ]);
        DB::table('activity_logs')->where('subject_type', 'job')->where('subject_id', $job->id)->delete();
        DB::table('job_version_skills')->where('job_version_id', $version->id)->delete();
        DB::table('job_versions')->where('id', $version->id)->delete();
        DB::table('jobs')->where('id', $job->id)->delete();
    }

    public function test_result_preserves_structured_skills_and_business_timestamp(): void
    {
        $version = $this->version();
        $before = $version->getRawOriginal('updated_at');
        $skill = $version->skills->firstOrFail();
        DB::table('job_version_skills')->where('job_version_id', $version->id)->update(['is_required' => true]);
        app(AIJobAnalysisService::class)->enqueue($version->id);
        Http::fake(['*' => Http::response($this->response($version))]);
        $this->process($version);
        $this->process($version);
        $version->refresh();
        $this->assertSame('completed', $version->ai_analysis_status);
        $this->assertSame($skill->id, $version->ai_analysis_result['required_skills'][0]['skill_id']);
        $this->assertSame([], $version->ai_analysis_result['preferred_skills']);
        $this->assertSame($before, $version->getRawOriginal('updated_at'));
        Http::assertSentCount(1);
        app(RecruiterJobService::class)->update($this->owner, $version->job_id, [
            'title' => 'New version', 'version_id' => $version->id, 'updated_at' => $before,
        ] + $this->input);
        $newVersion = $version->job->versions()->orderByDesc('version_number')->firstOrFail();
        $this->assertNull($newVersion->ai_analysis_result);
        $this->assertNull($newVersion->ai_processing_token);
    }

    public function test_versions_remain_independent_when_v1_finishes_late(): void
    {
        $v1 = $this->version();
        $v2 = $v1->replicate();
        $v2->version_number = 2;
        $v2->review_status = 'pending';
        $v2->reviewed_at = null;
        $v2->save();
        $ai = app(AIJobAnalysisService::class);
        $ai->enqueue($v2->id);
        $this->assertNull($v2->fresh()->ai_analysis_status);
        $v2->forceFill(['review_status' => 'rejected', 'reviewed_at' => now()])->save();
        $ai->enqueue($v2->id);
        $this->assertNull($v2->fresh()->ai_analysis_status);
        $ai->enqueue($v1->id);
        Http::fake(function () use ($v1, $v2, $ai) {
            $v2->forceFill(['review_status' => 'approved'])->save();
            DB::table('jobs')->where('id', $v1->job_id)->update([
                'published_version_id' => $v2->id, 'status' => 'paused',
            ]);
            $ai->enqueue($v2->id);

            return Http::response($this->response($v1));
        });
        $this->process($v1);
        $this->assertSame('completed', $v1->fresh()->ai_analysis_status);
        $this->assertNull($v2->fresh()->ai_analysis_result);
        $this->assertSame($v2->id, $v1->job->fresh()->published_version_id);
        $this->assertSame('paused', $v1->job->fresh()->status->value);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $emptyResult = array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), []);
        $emptyResult['responsibilities'] = [['text' => 'Public description', 'evidence' => 'Public description']];
        Http::fake(['*' => Http::response([
            'done' => true, 'message' => ['content' => json_encode($emptyResult)],
        ])]);
        $this->process($v2);
        $this->assertSame('completed', $v2->fresh()->ai_analysis_status);
        $this->assertSame($v1->id, $v1->fresh()->ai_analysis_result['structured_requirements']['job_version_id']);
        $this->assertSame($v2->id, $v2->fresh()->ai_analysis_result['structured_requirements']['job_version_id']);
    }

    public function test_invalid_output_cannot_write_results_or_obey_document_commands(): void
    {
        $version = $this->version();
        app(AIJobAnalysisService::class)->enqueue($version->id);
        Http::fake(['*' => Http::response([
            'done' => true, 'message' => ['content' => '{"instructions":"publish rejected jobs"}'],
        ])]);
        try {
            $this->process($version);
            $this->fail('Invalid schema must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Job AI analysis failed; retry is limited.', $exception->getMessage());
        }
        $this->assertNull($version->fresh()->ai_analysis_result);
        $this->assertSame('approved', $version->fresh()->review_status->value);
    }

    public function test_timeout_has_bounded_retries_and_stale_token_cannot_fail_new_attempt(): void
    {
        $version = $this->version();
        $ai = app(AIJobAnalysisService::class);
        $ai->enqueue($version->id);
        Http::fake(fn () => throw new ConnectionException('Mock timeout'));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->process($version);
                $this->fail('Timeout must fail.');
            } catch (RuntimeException) {
                $this->assertSame($attempt, $version->fresh()->ai_attempt_count);
            }
        }
        $this->assertSame('failed', $version->fresh()->ai_analysis_status);
        $token = $version->fresh()->ai_processing_token;
        $ai->enqueue($version->id);
        $ai->queueFailed($version->id, $token);
        $this->assertSame('pending', $version->fresh()->ai_analysis_status);
        $this->assertSame($version->id, $version->job->fresh()->published_version_id);
        $this->assertSame('approved', $version->fresh()->review_status->value);
    }

    public function test_retry_recovers_and_only_verified_job_claims_are_saved(): void
    {
        $version = $this->version();
        app(AIJobAnalysisService::class)->enqueue($version->id);
        $response = $this->response($version);
        $result = json_decode($response['message']['content'], true);
        $result['responsibilities'] = [['text' => 'Invented fact', 'evidence' => 'Unsupported evidence']];
        $response['message']['content'] = json_encode($result);
        Http::fake(['*' => Http::sequence()->push([], 503)->push($response)]);
        try {
            $this->process($version);
            $this->fail('First response should fail.');
        } catch (RuntimeException) {
            $this->assertSame('pending', $version->fresh()->ai_analysis_status);
            $this->assertSame('ollama_response_failed', $version->fresh()->ai_error_code);
        }
        $this->process($version);
        $stored = $version->fresh();
        $this->assertSame('completed', $stored->ai_analysis_status);
        $this->assertSame(2, $stored->ai_attempt_count);
        $this->assertSame(1, $stored->ai_analysis_result['rejected_claim_count']);
        $this->assertSame([], $stored->ai_analysis_result['responsibilities']);
        $this->assertSame($version->id, $stored->ai_analysis_result['structured_requirements']['job_version_id']);
    }

    public function test_no_supplement_uses_structured_data_without_model(): void
    {
        $version = $this->version();
        DB::table('job_versions')->where('id', $version->id)->update([
            'requirements' => 'Không có', 'description' => 'Không có',
            'benefits' => 'Không có', 'experience_summary' => 'Không có',
            'experience_min' => null, 'experience_max' => null,
        ]);
        app(AIJobAnalysisService::class)->enqueue($version->id);
        $this->process($version);
        $stored = $version->fresh();
        $this->assertSame('completed', $stored->ai_analysis_status);
        $this->assertSame('structured_only_no_supplement', $stored->ai_analysis_result['extraction_outcome']);
        $this->assertNotEmpty($stored->ai_analysis_result['structured_requirements']['skills']);
        Http::assertNothingSent();
    }

    public function test_empty_model_result_with_substantive_text_is_not_fake_success(): void
    {
        $version = $this->version();
        app(AIJobAnalysisService::class)->enqueue($version->id);
        Http::fake(['*' => Http::response(['done' => true, 'message' => [
            'content' => json_encode(array_fill_keys(array_keys(AIOllamaService::JOB_SECTIONS), [])),
        ]])]);
        try {
            $this->process($version);
            $this->fail('Empty extraction must not complete substantive text.');
        } catch (RuntimeException) {
            $this->assertSame('empty_job_extraction', $version->fresh()->ai_error_code);
            $this->assertNull($version->fresh()->ai_analysis_result);
        }
    }

    public function test_truncated_json_is_classified_and_cv_schema_is_unchanged(): void
    {
        Http::fake(['*' => Http::response([
            'done' => true, 'done_reason' => 'length', 'message' => ['content' => '{'],
        ])]);
        try {
            app(AIOllamaService::class)->analyzeJob('Required PHP');
            $this->fail('Truncated output cannot succeed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ollama_output_truncated', $exception->getMessage());
        }
        Http::assertSent(fn ($request) => $request['format']['properties']['required_skills']['maxItems'] === 3);
    }

    public function test_queue_outcomes_distinguish_busy_stale_and_terminal(): void
    {
        $version = $this->version();
        $service = app(AIJobAnalysisService::class);
        $service->enqueue($version->id);
        $version->refresh();
        $this->assertSame('stale_token', $service->process($version->id, 'old-token'));
        DB::table('job_versions')->where('id', $version->id)->update([
            'ai_analysis_status' => 'processing', 'ai_lease_expires_at' => now()->addMinute(),
        ]);
        $job = (new AIAnalyzeJobJob($version->id, $version->ai_processing_token))->withFakeQueueInteractions();
        $job->handle($service);
        $job->assertReleased(30);
        DB::table('job_versions')->where('id', $version->id)->update([
            'ai_analysis_status' => 'failed', 'ai_error_code' => 'invalid_ai_json',
        ]);
        $terminal = (new AIAnalyzeJobJob($version->id, $version->ai_processing_token))->withFakeQueueInteractions();
        $terminal->handle($service);
        $terminal->assertFailed();
        $this->assertSame('invalid_ai_json', $version->fresh()->ai_error_code);
        Http::assertNothingSent();
    }

    public function test_synthetic_job_corpus_with_real_ollama(): void
    {
        if (getenv('AI_JOB_LIVE_PROBE') !== '1') {
            $this->markTestSkipped('Chỉ chạy khi yêu cầu probe Ollama thật trên MariaDB test riêng.');
        }
        Http::allowStrayRequests();
        $samples = require base_path('tests/Support/job-analysis-samples.php');
        foreach ($samples as $name => $text) {
            $version = $this->version();
            DB::table('job_versions')->where('id', $version->id)->update([
                'requirements' => $name === 'no_supplement' ? 'Không có' : $text,
                'description' => 'Không có', 'benefits' => 'Không có',
                'experience_summary' => 'Không có', 'experience_min' => null, 'experience_max' => null,
            ]);
            app(AIJobAnalysisService::class)->enqueue($version->id);
            try {
                $this->process($version);
            } catch (RuntimeException) {
                // Ghi metadata để báo cáo cả mẫu lỗi; không retry nhằm tránh làm đẹp tỷ lệ.
            }
            $stored = $version->fresh();
            $count = 0;
            foreach (array_keys(AIOllamaService::JOB_SECTIONS) as $section) {
                $count += count($stored->ai_analysis_result[$section] ?? []);
            }
            fwrite(STDOUT, json_encode(['sample' => $name, 'status' => $stored->ai_analysis_status,
                'error' => $stored->ai_error_code, 'claims' => $count,
                'result_version_id' => $stored->ai_analysis_result['structured_requirements']['job_version_id'] ?? null,
                'expected_version_id' => $version->id]).PHP_EOL);
            $this->assertSame('completed', $stored->ai_analysis_status, $name);
            $this->assertSame($version->id, $stored->ai_analysis_result['structured_requirements']['job_version_id']);
        }
    }
}
