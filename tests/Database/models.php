<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ($app->configurationIsCached()) {
    fwrite(STDERR, "Cached configuration prevents safe test database isolation.\n");
    exit(1);
}

$originalName = config('database.default');
$original = DB::connection();
$configuration = config('database.connections.'.$originalName);
$database = 'viejob_models_test_'.bin2hex(random_bytes(6));
$configuration['database'] = $database;
$configuration['url'] = null;
unset($configuration['name']);
config(['database.connections.models_test' => $configuration]);

function schemaDefinitions(Connection $connection): array
{
    $definitions = [];
    foreach ($connection->select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $definition = array_values((array) $connection->selectOne("SHOW CREATE TABLE `$table`"))[1];
        $definition = preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition);
        // ALTER có thể đổi thứ tự CHECK trong SHOW CREATE, không đổi nội dung ràng buộc.
        preg_match_all('/^\s*CONSTRAINT .*$/m', $definition, $matches);
        $constraints = array_map(fn ($line) => rtrim(trim($line), ','), $matches[0]);
        sort($constraints);
        $definitions[$table] = preg_replace('/^\s*CONSTRAINT .*\n?/m', '', $definition).implode("\n", $constraints);
    }
    ksort($definitions);

    return $definitions;
}

$before = schemaDefinitions($original);
$migrationsBefore = $original->table('migrations')->orderBy('id')->get()->toJson();
$original->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    DB::setDefaultConnection('models_test');
    $testConnection = DB::connection('models_test');
    if ($testConnection->getName() !== 'models_test' || $testConnection->getDatabaseName() !== $database) {
        throw new RuntimeException('Unsafe test connection identity.');
    }
    if (Artisan::call('migrate', ['--database' => 'models_test', '--force' => true]) !== 0) {
        throw new RuntimeException('Cannot prepare model test schema.');
    }
    $pendingPositions = in_array('--pending-job-positions', $argv, true);
    $positionsMigration = 'database/migrations/2026_11_19_120000_add_job_positions_and_province.php';
    if ($pendingPositions) {
        if ($original->table('migrations')->where('migration', basename($positionsMigration, '.php'))->exists()) {
            throw new RuntimeException('Position migration already applied; omit pending flag.');
        }
        if (Artisan::call('migrate:rollback', ['--database' => 'models_test', '--path' => [$positionsMigration], '--step' => 1, '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot rehearse position rollback.');
        }
    }
    $pendingCompany = in_array('--pending-company-profile', $argv, true);
    $companyMigration = 'database/migrations/2026_11_18_120000_simplify_company_profile.php';
    if ($pendingCompany) {
        if ($original->table('migrations')->where('migration', basename($companyMigration, '.php'))->exists()) {
            throw new RuntimeException('Company migration already applied; omit pending flag.');
        }
        if (Artisan::call('migrate:rollback', ['--database' => 'models_test', '--path' => [$companyMigration], '--step' => 1, '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot rehearse company rollback.');
        }
    }
    $pendingPasswordSetup = in_array('--pending-password-setup', $argv, true);
    $pendingJobDrafts = in_array('--pending-job-drafts', $argv, true);
    $jobDraftMigration = 'database/migrations/2026_11_17_100000_allow_incomplete_job_drafts.php';
    if ($pendingJobDrafts) {
        if ($original->table('migrations')->where('migration', basename($jobDraftMigration, '.php'))->exists()) {
            throw new RuntimeException('Job draft migration already applied; omit --pending-job-drafts.');
        }
        if (Artisan::call('migrate:rollback', ['--database' => 'models_test', '--path' => [$jobDraftMigration], '--step' => 1, '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot rehearse draft migration rollback.');
        }
    }
    if ($pendingPasswordSetup) {
        $passwordMigration = 'database/migrations/2026_11_17_000000_add_has_local_password_to_users_table.php';
        if ($original->table('migrations')->where('migration', basename($passwordMigration, '.php'))->exists()) {
            throw new RuntimeException('Password setup migration already applied; omit --pending-password-setup.');
        }
        if (Artisan::call('migrate:rollback', ['--database' => 'models_test', '--path' => [$passwordMigration], '--step' => 1, '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot rehearse password classification rollback.');
        }
    }
    if (in_array('--pending-google', $argv, true)) {
        $googleMigration = 'database/migrations/2026_11_16_210000_add_google_subject_to_users_table.php';
        if ($original->table('migrations')->where('migration', basename($googleMigration, '.php'))->exists()) {
            throw new RuntimeException('Google migration already applied; omit --pending-google.');
        }
        Artisan::call('migrate:rollback', ['--database' => 'models_test', '--path' => [$googleMigration], '--step' => 1, '--force' => true]);
    }
    if (schemaDefinitions($testConnection) !== $before) {
        throw new RuntimeException('Live schema differs from migrations; report drift before testing models.');
    }
    if ($pendingPasswordSetup) {
        if (Artisan::call('migrate', ['--database' => 'models_test', '--path' => [$passwordMigration], '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot apply pending password classification on isolated database.');
        }
    }
    if (in_array('--pending-google', $argv, true)) {
        if (Artisan::call('migrate', ['--database' => 'models_test', '--path' => [$googleMigration], '--force' => true]) !== 0) {
            throw new RuntimeException('Cannot apply pending Google migration on isolated database.');
        }
    }
    if ($pendingCompany && Artisan::call('migrate', ['--database' => 'models_test', '--path' => [$companyMigration], '--force' => true]) !== 0) {
        throw new RuntimeException('Cannot apply company migration on test database.');
    }
    if ($pendingPositions && Artisan::call('migrate', ['--database' => 'models_test', '--path' => [$positionsMigration], '--force' => true]) !== 0) {
        throw new RuntimeException('Cannot apply positions migration on test database.');
    }
    $testConfiguration = in_array('--auth', $argv, true) ? 'tests/Auth/phpunit.xml' : 'tests/Models/phpunit.xml';
    if ($pendingJobDrafts && Artisan::call('migrate', ['--database' => 'models_test', '--path' => [$jobDraftMigration], '--force' => true]) !== 0) {
        throw new RuntimeException('Cannot apply pending draft migration on isolated database.');
    }
    $phpArguments = in_array('--with-gd', $argv, true) ? ['-d', 'extension=gd'] : [];
    $testArguments = in_array('--moderation', $argv, true) ? ['--filter', 'JobModerationTest|RecruiterJobTest|AdminPageTest|AuthenticationTest|PoliciesTest|GooglePreparationTest|GooglePasswordSetupTest|PasswordRecoveryTest'] : [];
    if (in_array('--job-review', $argv, true)) {
        $testArguments = ['--filter', 'RecruiterJobTest|JobModerationTest'];
    }
    if (in_array('--candidate-applications', $argv, true)) {
        $testArguments = ['--filter', 'CandidateApplicationsTest|CandidatePreviewTest|RecruiterJobTest|JobModerationTest|PoliciesTest|AuthenticationTest'];
    }
    if (in_array('--recruiter-applications', $argv, true)) {
        $testArguments = ['--filter', 'RecruiterApplicationsTest|RecruiterPageTest|CandidateApplicationsTest|RecruiterJobTest|PoliciesTest|AuthenticationTest'];
    }
    if (in_array('--saved-jobs', $argv, true)) {
        $testArguments = ['--filter', 'CandidateSavedJobsTest|CandidatePreviewTest|RecruiterJobTest|JobModerationTest|PoliciesTest|AuthenticationTest'];
    }
    if (in_array('--candidate-profile', $argv, true)) {
        $testArguments = ['--filter', 'CandidateProfileTest|CandidatePreviewTest|CandidateSavedJobsTest|AuthenticationTest|GoogleAuthenticationTest|GooglePasswordSetupTest|PasswordRecoveryTest'];
    }
    $process = new Process([PHP_BINARY, ...$phpArguments, 'vendor/phpunit/phpunit/phpunit', '--configuration', $testConfiguration, '--fail-on-empty-test-suite', ...$testArguments], base_path(), [
        'APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $database, 'DB_HOST' => $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
        'DB_USERNAME' => $configuration['username'], 'DB_PASSWORD' => (string) $configuration['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array', 'MAIL_HOST' => 'smtp.test.invalid', 'MAIL_PORT' => '2525',
        'MAIL_USERNAME' => '', 'MAIL_PASSWORD' => '', 'MAIL_URL' => '', 'PASSWORD_RECOVERY_ENABLED' => 'false',
    ]);
    $process->setTimeout(300);
    $process->run(function (string $type, string $output): void {
        echo $output;
    });
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Model test suite failed.');
    }
    if (schemaDefinitions($original) !== $before || $original->table('migrations')->orderBy('id')->get()->toJson() !== $migrationsBefore) {
        throw new RuntimeException('Original schema/history changed during test run.');
    }
    echo "PASS: tests used an isolated MariaDB database; original schema and migration history remain unchanged.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $failed = true;
} finally {
    DB::purge('models_test');
    DB::setDefaultConnection($originalName);
    if (preg_match('/^viejob_models_test_[a-f0-9]{12}$/', $database)) {
        $original->statement("DROP DATABASE `$database`");
    }
}

exit(isset($failed) ? 1 : 0);
