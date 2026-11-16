<?php

// Standalone integration suite: only writes to a new, randomly named database.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$originalConnection = config('database.default');
$original = DB::connection($originalConnection);
$version = $original->selectOne('SELECT VERSION() AS version')->version;

if (! str_contains($version, 'MariaDB')) {
    throw new RuntimeException('Run this suite against MariaDB, not SQLite.');
}

$database = 'viejob_schema_test_'.bin2hex(random_bytes(6));
$configuration = config('database.connections.'.$originalConnection);
$configuration['database'] = $database;
$configuration['url'] = null;
config(['database.connections.schema_test' => $configuration]);
$original->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
DB::setDefaultConnection('schema_test');
$connection = DB::connection('schema_test');
$assertions = 0;

function verify(bool $condition, string $message): void
{
    global $assertions;

    if (! $condition) {
        throw new RuntimeException($message);
    }

    $assertions++;
}

function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (QueryException $exception) {
        verify(in_array($exception->errorInfo[1], [1062, 1451, 1452, 4025, 1048]), $message.': unexpected SQL error');

        return;
    }

    throw new RuntimeException('Constraint did not reject: '.$message);
}

function insertRow(string $table, array $values): int
{
    $timestamp = '2026-11-15 10:00:00.000000';

    return DB::table($table)->insertGetId($values + ['created_at' => $timestamp, 'updated_at' => $timestamp]);
}

try {
    // Original migrations form batch 1; the new migrations form batch 2.
    $baseline = glob(__DIR__.'/../../database/migrations/0001_*.php');
    verify(Artisan::call('migrate', ['--database' => 'schema_test', '--path' => $baseline, '--realpath' => true, '--force' => true]) === 0, 'Baseline migrations');
    $payload = json_encode(['schema_test' => 'queue payload must survive rename']);
    $queueId = DB::table('jobs')->insertGetId([
        'queue' => 'schema-test', 'payload' => $payload, 'attempts' => 2,
        'reserved_at' => null, 'available_at' => 123, 'created_at' => 123,
    ]);

    verify(Artisan::call('migrate', ['--database' => 'schema_test', '--force' => true]) === 0, 'Business migrations');
    $tables = array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));
    verify(count($tables) === 32, 'Exactly 32 tables');
    $expected = explode(' ', 'users candidate_profiles companies industries job_categories job_specializations skills job_levels jobs job_versions job_version_skills job_moderation_logs resumes resume_revisions resume_skills applications application_status_histories application_notes saved_jobs recommendation_runs recommendation_items application_ai_evaluations activity_logs notifications queue_jobs failed_jobs job_batches cache cache_locks password_reset_tokens migrations sessions');
    sort($tables);
    sort($expected);
    verify($tables === $expected, 'Exact approved table inventory');
    $queue = DB::table('queue_jobs')->find($queueId);
    verify($queue->payload === $payload && $queue->attempts === 2, 'Queue data preserved');
    verify(config('queue.connections.database.after_commit') === true, 'Queue dispatch after commit');

    $columns = DB::select("SELECT TABLE_NAME, COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('pending_slot', 'primary_slot')", [$database]);
    verify(count($columns) === 2, 'Both generated slots exist');
    foreach ($columns as $column) {
        verify(str_contains($column->EXTRA, 'STORED GENERATED'), 'Slot is stored generated');
    }

    $connection->beginTransaction();
    $candidate = insertRow('users', ['name' => 'Test Candidate', 'email' => 'candidate@test.invalid', 'password' => 'test-only', 'role' => 'candidate']);
    $otherCandidate = insertRow('users', ['name' => 'Other Candidate', 'email' => 'other@test.invalid', 'password' => 'test-only', 'role' => 'candidate']);
    $recruiter = insertRow('users', ['name' => 'Recruiter', 'email' => 'recruiter@test.invalid', 'password' => 'test-only', 'role' => 'recruiter']);
    $admin = insertRow('users', ['name' => 'Admin', 'email' => 'admin@test.invalid', 'password' => 'test-only', 'role' => 'admin']);
    rejects(fn () => DB::table('users')->where('id', $candidate)->update(['role' => 'invalid']), 'Role CHECK');
    $catalog = ['code' => 'TEST', 'name' => 'Test', 'normalized_name' => 'test', 'slug' => 'test'];
    $industry = insertRow('industries', $catalog);
    $level = insertRow('job_levels', $catalog);
    $category = insertRow('job_categories', $catalog);
    $specialization = insertRow('job_specializations', $catalog + ['job_category_id' => $category]);
    $skill = insertRow('skills', ['code' => 'PHP', 'name' => 'PHP', 'normalized_name' => 'php']);
    $company = insertRow('companies', [
        'public_id' => (string) Str::uuid(), 'recruiter_id' => $recruiter, 'name' => 'Test Company',
        'slug' => 'test-company', 'office_images' => '[]', 'email' => 'company@test.invalid', 'phone' => '0900000000', 'address' => 'Test',
    ]);
    rejects(fn () => DB::table('companies')->where('id', $company)->update(['office_images' => '{}']), 'Office images must be array');
    rejects(fn () => DB::table('companies')->where('id', $company)->update(['office_images' => json_encode(array_fill(0, 7, ['path' => 'test']))]), 'Maximum six images');
    rejects(fn () => DB::table('companies')->where('id', $company)->update(['office_images' => 'invalid']), 'Invalid JSON');
    $job = insertRow('jobs', ['company_id' => $company]);
    $otherJob = insertRow('jobs', ['company_id' => $company]);
    $versionValues = [
        'job_id' => $job, 'version_number' => 1, 'title' => 'Backend', 'position' => 'Developer',
        'description' => 'Test', 'requirements' => 'PHP', 'benefits' => 'Test',
        'salary_min' => 10000000, 'salary_max' => 20000000, 'location' => 'Test',
        'work_mode' => 'onsite', 'employment_type' => 'full_time', 'quantity' => 1,
        'experience_summary' => 'Test', 'job_specialization_id' => $specialization, 'application_deadline' => '2027-01-01',
    ];
    $draft = insertRow('job_versions', $versionValues);
    $secondDraft = insertRow('job_versions', array_replace($versionValues, ['version_number' => 2]));
    rejects(fn () => DB::table('job_versions')->where('id', $draft)->update(['review_status' => 'pending', 'submitted_at' => now()]), 'Industry and level required on submission');
    $pendingFields = ['review_status' => 'pending', 'submitted_at' => now(), 'industry_id' => $industry, 'job_level_id' => $level];
    DB::table('job_versions')->where('id', $draft)->update($pendingFields);
    rejects(fn () => DB::table('job_versions')->where('id', $secondDraft)->update($pendingFields), 'One pending per job');
    rejects(fn () => DB::table('jobs')->where('id', $otherJob)->update(['published_version_id' => $draft, 'published_at' => now(), 'expires_at' => now()]), 'Published version same job');
    rejects(fn () => DB::table('job_versions')->where('id', $draft)->update(['salary_min' => 30000000]), 'Salary range');
    DB::table('job_versions')->where('id', $draft)->update(['review_status' => 'approved', 'reviewed_at' => now()]);
    DB::table('jobs')->where('id', $job)->update(['published_version_id' => $draft, 'published_at' => now(), 'expires_at' => now()]);

    $resume = insertRow('resumes', ['public_id' => (string) Str::uuid(), 'candidate_id' => $candidate, 'display_name' => 'CV', 'current_revision' => 1, 'is_primary' => true]);
    $secondResume = insertRow('resumes', ['public_id' => (string) Str::uuid(), 'candidate_id' => $candidate, 'display_name' => 'CV 2', 'current_revision' => 1]);
    rejects(fn () => DB::table('resumes')->where('id', $secondResume)->update(['is_primary' => true]), 'One primary CV per candidate');
    rejects(fn () => DB::table('resumes')->where('id', $resume)->update(['deleted_at' => now()]), 'Deleted CV cannot be primary');
    $hash = str_repeat('a', 64);
    insertRow('resume_revisions', ['resume_id' => $resume, 'revision' => 1, 'file_path' => 'private/test.pdf', 'original_filename' => 'test.pdf', 'file_size' => 100, 'mime_type' => 'application/pdf', 'file_hash' => $hash]);
    rejects(fn () => insertRow('resume_skills', ['resume_id' => $resume, 'resume_revision' => 2, 'skill_id' => $skill]), 'Revision foreign key');
    $applicationValues = [
        'public_id' => (string) Str::uuid(), 'candidate_id' => $candidate, 'job_id' => $job, 'attempt_no' => 1,
        'resume_id' => $resume, 'resume_revision' => 1, 'resume_snapshot_path' => 'snapshots/test.pdf',
        'resume_snapshot_hash' => $hash, 'resume_snapshot_filename' => 'test.pdf', 'resume_snapshot_size' => 100,
        'job_version_id' => $draft, 'applied_at' => now(),
    ];
    rejects(fn () => insertRow('applications', array_replace($applicationValues, ['candidate_id' => $otherCandidate])), 'Resume owner composite FK');
    rejects(fn () => insertRow('applications', array_replace($applicationValues, ['attempt_no' => 3])), 'Maximum two attempts');
    $application = insertRow('applications', $applicationValues);
    $evaluation = ['application_id' => $application, 'job_id' => $job, 'job_version_id' => $draft, 'resume_snapshot_hash' => $hash, 'model_version' => 'test-model', 'formula_version' => 'v1'];
    $evaluationId = insertRow('application_ai_evaluations', $evaluation);
    rejects(fn () => insertRow('application_ai_evaluations', $evaluation), 'AI idempotency');
    rejects(fn () => DB::table('application_ai_evaluations')->where('id', $evaluationId)->update(['evaluation_status' => 'completed']), 'Completed AI requires results');
    rejects(fn () => DB::table('application_ai_evaluations')->where('id', $evaluationId)->update(['score' => 101]), 'Score 0 to 100');
    $audit = ['actor_id' => $admin, 'subject_type' => 'user', 'subject_id' => $candidate, 'action' => 'user.lock', 'old_values' => '{"status":"active"}', 'new_values' => '{"status":"locked"}', 'reason' => 'Test', 'created_at' => now()];
    DB::table('activity_logs')->insert($audit);
    DB::table('activity_logs')->insert($audit);
    verify(DB::table('activity_logs')->count() === 2, 'Repeated lifecycle actions can be recorded');
    rejects(fn () => DB::table('activity_logs')->insert(array_replace($audit, ['reason' => null])), 'Lock reason cannot be null');
    rejects(fn () => DB::table('activity_logs')->insert(array_replace($audit, ['subject_type' => 'arbitrary-class'])), 'Morph whitelist');
    rejects(fn () => DB::table('users')->where('id', $candidate)->delete(), 'Restrict historical owner deletion');
    $connection->rollBack();

    // Two independent connections compete for the primary CV unique slot.
    $connection->beginTransaction();
    $concurrentCandidate = insertRow('users', ['name' => 'Concurrent', 'email' => 'concurrent@test.invalid', 'password' => 'test-only', 'role' => 'candidate']);
    $firstResume = insertRow('resumes', ['public_id' => (string) Str::uuid(), 'candidate_id' => $concurrentCandidate, 'display_name' => 'First', 'current_revision' => 1]);
    $competingResume = insertRow('resumes', ['public_id' => (string) Str::uuid(), 'candidate_id' => $concurrentCandidate, 'display_name' => 'Second', 'current_revision' => 1]);
    $connection->commit();
    config(['database.connections.schema_competing' => $configuration]);
    $competing = DB::connection('schema_competing');
    $competing->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $connection->beginTransaction();
    DB::table('resumes')->where('id', $firstResume)->update(['is_primary' => true]);
    try {
        $competing->table('resumes')->where('id', $competingResume)->update(['is_primary' => true]);
        throw new RuntimeException('Concurrent primary update bypassed unique slot lock.');
    } catch (QueryException $exception) {
        verify($exception->errorInfo[1] === 1205, 'Concurrent primary update waits for slot lock');
    }
    $connection->commit();
    rejects(fn () => $competing->table('resumes')->where('id', $competingResume)->update(['is_primary' => true]), 'Concurrent loser cannot commit a second primary');
    verify(DB::table('resumes')->where('candidate_id', $concurrentCandidate)->where('is_primary', true)->count() === 1, 'Exactly one primary after concurrent writes');
    DB::table('resumes')->where('candidate_id', $concurrentCandidate)->delete();
    DB::table('users')->where('id', $concurrentCandidate)->delete();
    DB::purge('schema_competing');

    // All business fixtures were rolled back. The queue sentinel must survive down/up.
    verify(Artisan::call('migrate:rollback', ['--database' => 'schema_test', '--force' => true]) === 0, 'Rollback empty business tables');
    verify(DB::table('jobs')->find($queueId)->payload === $payload, 'Queue preserved on rollback');
    verify(count(DB::select('SHOW TABLES')) === 9, 'Rollback returns to baseline schema');
    verify(Artisan::call('migrate', ['--database' => 'schema_test', '--force' => true]) === 0, 'Migrations run again');
    echo "PASS: $assertions assertions on $version; fresh install, queue preservation, constraints, rollback and reapply.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n".Artisan::output());
    $failed = true;
} finally {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    DB::purge('schema_test');
    DB::setDefaultConnection($originalConnection);
    // Only the random database created by this invocation can be removed.
    if (preg_match('/^viejob_schema_test_[a-f0-9]{12}$/', $database)) {
        $original->statement("DROP DATABASE `$database`");
    }
}

exit(isset($failed) ? 1 : 0);
