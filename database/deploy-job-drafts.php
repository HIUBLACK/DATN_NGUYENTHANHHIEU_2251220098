<?php

// Chỉ triển khai forward migration bản nháp đã được phê duyệt, không dùng cho baseline/reset.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') !== '--apply' || $app->configurationIsCached()) {
    fwrite(STDERR, "Requires --apply and uncached configuration.\n");
    exit(1);
}

$source = DB::connection();
$configuration = $source->getConfig();
$migrationPath = 'database/migrations/2026_11_17_100000_allow_incomplete_job_drafts.php';
$migrationName = basename($migrationPath, '.php');
$restoreName = 'viejob_drafts_restore_'.bin2hex(random_bytes(6));
$backupDirectory = storage_path('app/private/database-backups/'.date('Ymd_His').'_job_drafts');
$createdRestore = false;
$maintenance = false;
$applied = false;

function draftManifest(Connection $connection): array
{
    $result = [];
    foreach ($connection->select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $records = array_map(fn ($record) => json_encode($record), $connection->table($table)->get()->all());
        sort($records);
        $result[$table] = ['count' => count($records), 'hash' => hash('sha256', implode("\n", $records))];
    }
    ksort($result);

    return $result;
}

function draftSchema(Connection $connection): array
{
    $result = [];
    foreach ($connection->select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $sql = array_values((array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
        $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
        preg_match_all('/^\s*CONSTRAINT .*$/m', $sql, $matches);
        $constraints = array_map(fn ($line) => rtrim(trim($line), ','), $matches[0]);
        sort($constraints);
        $result[$table] = preg_replace('/^\s*CONSTRAINT .*\n?/m', '', $sql).implode("\n", $constraints);
    }
    ksort($result);

    return $result;
}

function draftUtility(array $arguments, array $configuration, mixed $input = null): void
{
    $process = new Process($arguments, base_path(), ['MYSQL_PWD' => (string) $configuration['password']]);
    $process->setTimeout(120);
    if ($input !== null) {
        $process->setInput($input);
    }
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Backup/restore utility failed with exit code '.$process->getExitCode().'. No SQL or credentials printed.');
    }
}

try {
    if ($source->getDatabaseName() !== 'db_job' || count($source->select('SHOW TABLES')) !== 32) {
        throw new RuntimeException('Expected the approved db_job database with 32 tables.');
    }
    if ($source->table('migrations')->where('migration', $migrationName)->exists()) {
        throw new RuntimeException('Approved migration already applied; do not rerun deployment.');
    }
    if (app()->isDownForMaintenance()) {
        throw new RuntimeException('Application is already in maintenance.');
    }
    $migration = require base_path($migrationPath);
    $migration->assertCompatibleData();
    Artisan::call('down', ['--retry' => 60]);
    $maintenance = true;
    $before = draftManifest($source);
    $beforeSchema = draftSchema($source);
    $beforeHistory = $source->table('migrations')->orderBy('id')->get()->toJson();
    if (! mkdir($backupDirectory, 0700, true) && ! is_dir($backupDirectory)) {
        throw new RuntimeException('Cannot create private backup directory.');
    }
    $arguments = ['--host='.$configuration['host'], '--port='.$configuration['port'], '--user='.$configuration['username'], '--default-character-set=utf8mb4'];
    if (in_array($configuration['host'], ['localhost', '127.0.0.1', '::1'], true)) {
        $arguments[] = '--skip-ssl';
    }
    $backup = $backupDirectory.'/before-migration.sql';
    draftUtility(['C:/xampp/mysql/bin/mariadb-dump.exe', ...$arguments, '--single-transaction', '--triggers', '--hex-blob', '--result-file='.$backup, 'db_job'], $configuration);
    if (! is_file($backup) || filesize($backup) === 0) {
        throw new RuntimeException('Empty backup.');
    }
    $source->statement("CREATE DATABASE `$restoreName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $createdRestore = true;
    $input = fopen($backup, 'rb');
    try {
        draftUtility(['C:/xampp/mysql/bin/mariadb.exe', ...$arguments, $restoreName], $configuration, $input);
    } finally {
        fclose($input);
    }
    $restoreConfig = $configuration;
    unset($restoreConfig['name']);
    $restoreConfig['database'] = $restoreName;
    $restoreConfig['url'] = null;
    config(['database.connections.drafts_restore' => $restoreConfig]);
    $restore = DB::connection('drafts_restore');
    if ($restore->getName() !== 'drafts_restore' || $restore->getDatabaseName() !== $restoreName
        || draftManifest($restore) !== $before || draftSchema($restore) !== $beforeSchema) {
        throw new RuntimeException('Restored backup does not match source.');
    }
    $options = ['--database' => 'drafts_restore', '--path' => [$migrationPath], '--force' => true];
    foreach (['migrate', 'migrate:rollback', 'migrate'] as $command) {
        if (Artisan::call($command, $options) !== 0) {
            throw new RuntimeException('Draft migration rehearsal failed: '.$command);
        }
    }
    $rehearsed = draftManifest($restore);
    foreach ($before as $table => $manifest) {
        if ($table !== 'migrations' && $manifest !== $rehearsed[$table]) {
            throw new RuntimeException('Rehearsal changed data: '.$table);
        }
    }
    if (draftManifest($source) !== $before || draftSchema($source) !== $beforeSchema) {
        throw new RuntimeException('Source changed during rehearsal; stop other producers before retrying.');
    }
    // Từ thời điểm bắt đầu DDL nguồn, lỗi giữ maintenance để kiểm tra, không tự rollback hoặc retry.
    $applied = true;
    if (Artisan::call('migrate', ['--path' => [$migrationPath], '--force' => true]) !== 0) {
        throw new RuntimeException('Approved migration failed; inspect source before resuming.');
    }
    $after = draftManifest($source);
    if (count($after) !== 32 || draftSchema($source) !== draftSchema($restore)) {
        throw new RuntimeException('Final schema differs from verified rehearsal.');
    }
    foreach ($before as $table => $manifest) {
        if ($table !== 'migrations' && $manifest !== $after[$table]) {
            throw new RuntimeException('Source data changed unexpectedly: '.$table);
        }
    }
    if ($source->table('migrations')->where('migration', '!=', $migrationName)->orderBy('id')->get()->toJson() !== $beforeHistory
        || $source->table('migrations')->where('migration', $migrationName)->count() !== 1) {
        throw new RuntimeException('Unexpected migration history.');
    }
    file_put_contents($backupDirectory.'/manifest.json', json_encode([
        'database' => 'db_job', 'migration' => $migrationName,
        'backup_sha256' => hash_file('sha256', $backup), 'restore_verified' => true, 'up_down_up_verified' => true,
        'backup_scope' => 'Project tables and triggers; stored routines excluded as in baseline backup procedure.',
        'before' => $before, 'after' => $after,
    ], JSON_PRETTY_PRINT));
    Artisan::call('up');
    $maintenance = false;
    echo "PASS: backup restore and up/down/up verified; approved migration applied; 32 tables and all data preserved.\n";
    echo 'Backup: '.$backupDirectory.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    if ($maintenance && ! $applied) {
        Artisan::call('up');
    } elseif ($maintenance) {
        fwrite(STDERR, "Application remains in maintenance for inspection.\n");
    }
    $failed = true;
} finally {
    DB::purge('drafts_restore');
    if ($createdRestore && preg_match('/^viejob_drafts_restore_[a-f0-9]{12}$/', $restoreName)) {
        $source->statement("DROP DATABASE `$restoreName`");
    }
}

exit(isset($failed) ? 1 : 0);
