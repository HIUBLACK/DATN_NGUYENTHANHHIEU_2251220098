<?php

// Run from the project root: php database/deploy-schema.php --apply
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Use --apply only after reviewing migrations and stopping queue workers/producers.\n");
    exit(1);
}

$connection = DB::connection();
$configuration = $connection->getConfig();
$database = $connection->getDatabaseName();
$dumpBinary = 'C:/xampp/mysql/bin/mariadb-dump.exe';
$clientBinary = 'C:/xampp/mysql/bin/mariadb.exe';
$backupDirectory = storage_path('app/private/database-backups/'.date('Ymd_His').'_'.bin2hex(random_bytes(3)));
$restoreDatabase = 'viejob_restore_test_'.bin2hex(random_bytes(6));
$restoreCreated = false;
$maintenanceStarted = false;

function runDatabaseProcess(array $arguments, array $configuration, mixed $input = null): void
{
    $process = new Process($arguments, base_path(), ['MYSQL_PWD' => (string) $configuration['password']]);
    $process->setTimeout(120);
    if ($input !== null) {
        $process->setInput($input);
    }
    $process->run();
    if (! $process->isSuccessful()) {
        // Do not include command/environment or potentially sensitive dump contents.
        $diagnostic = str_replace((string) ($configuration['password'] ?: '__no_password__'), '[redacted]', $process->getErrorOutput());
        throw new RuntimeException('Database backup/restore utility failed. Exit code: '.$process->getExitCode().' '.$diagnostic);
    }
}

function databaseManifest(Connection $connection): array
{
    $manifest = [];
    foreach ($connection->select('SHOW TABLES') as $tableRow) {
        $table = array_values((array) $tableRow)[0];
        if (! preg_match('/^[a-z_]+$/', $table)) {
            throw new RuntimeException('Unexpected table identifier.');
        }
        $rows = array_map(fn ($row) => json_encode($row), $connection->table($table)->get()->all());
        sort($rows);
        $manifest[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
    ksort($manifest);

    return $manifest;
}

try {
    if (! is_file($dumpBinary) || ! is_file($clientBinary)) {
        throw new RuntimeException('MariaDB backup utilities are not available at the configured paths.');
    }
    if (app()->isDownForMaintenance()) {
        throw new RuntimeException('Application is already in maintenance; inspect the existing deployment first.');
    }
    if (DB::getSchemaBuilder()->hasTable('queue_jobs') || ! DB::getSchemaBuilder()->hasColumns('jobs', ['queue', 'payload'])) {
        throw new RuntimeException('Expected the original queue schema. This deployment is not a repeatable reset.');
    }
    if ($connection->table('users')->exists()) {
        throw new RuntimeException('Existing accounts need an approved role mapping.');
    }
    if (config('queue.connections.database.table') !== 'queue_jobs') {
        throw new RuntimeException('Set DB_QUEUE_TABLE=queue_jobs and clear cached configuration before cutover.');
    }
    Artisan::call('down', ['--retry' => 60]);
    $maintenanceStarted = true;
    if (! mkdir($backupDirectory, 0700, true) && ! is_dir($backupDirectory)) {
        throw new RuntimeException('Cannot create private backup directory.');
    }
    $before = databaseManifest($connection);
    $backup = $backupDirectory.'/before-migration.sql';
    $arguments = [
        '--host='.$configuration['host'], '--port='.$configuration['port'],
        '--user='.$configuration['username'], '--default-character-set=utf8mb4',
    ];
    // Windows CLI TLS fails on this local passwordless development server.
    // Never disable transport encryption for a remote database host.
    if (in_array($configuration['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
        $arguments[] = '--skip-ssl';
    }
    runDatabaseProcess(array_merge([$dumpBinary], $arguments, [
        '--single-transaction', '--triggers', '--hex-blob', '--result-file='.$backup, $database,
    ]), $configuration);
    if (! is_file($backup) || filesize($backup) === 0) {
        throw new RuntimeException('Backup is empty.');
    }
    $connection->statement("CREATE DATABASE `$restoreDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $restoreCreated = true;
    $input = fopen($backup, 'rb');
    try {
        runDatabaseProcess(array_merge([$clientBinary], $arguments, [$restoreDatabase]), $configuration, $input);
    } finally {
        fclose($input);
    }
    $restoreConfiguration = $configuration;
    unset($restoreConfiguration['name']);
    $restoreConfiguration['database'] = $restoreDatabase;
    $restoreConfiguration['url'] = null;
    config(['database.connections.schema_restore' => $restoreConfiguration]);
    $restore = DB::connection('schema_restore');
    if ($restore->getName() !== 'schema_restore' || $restore->getDatabaseName() !== $restoreDatabase) {
        throw new RuntimeException('Restore connection identity mismatch.');
    }
    if (databaseManifest($restore) !== $before) {
        throw new RuntimeException('Restored backup differs from source.');
    }
    if (Artisan::call('migrate', ['--database' => 'schema_restore', '--force' => true]) !== 0) {
        throw new RuntimeException('Rehearsal migration failed.');
    }
    if (count($restore->select('SHOW TABLES')) !== 32) {
        throw new RuntimeException('Rehearsal did not produce 32 tables.');
    }
    if (databaseManifest($connection) !== $before) {
        throw new RuntimeException('Source changed during maintenance; stop producers before retrying.');
    }
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Migration failed; application remains in maintenance.');
    }
    $after = databaseManifest($connection);
    if (count($after) !== 32) {
        throw new RuntimeException('Unexpected final table count.');
    }
    foreach ($before as $table => $manifest) {
        if ($table === 'migrations') {
            continue;
        }
        $target = $table === 'jobs' ? 'queue_jobs' : $table;
        if ($after[$target] !== $manifest) {
            throw new RuntimeException('Existing data changed unexpectedly: '.$target);
        }
    }
    file_put_contents($backupDirectory.'/manifest.json', json_encode([
        'database' => $database, 'server' => $connection->selectOne('SELECT VERSION() AS version')->version,
        'backup_sha256' => hash_file('sha256', $backup), 'restore_verified' => true,
        'backup_scope' => 'Project tables and triggers; stored routines excluded because mysql.proc is unreadable on the local server.',
        'before' => $before, 'after' => $after,
    ], JSON_PRETTY_PRINT));
    Artisan::call('up');
    echo "PASS: backup restored and verified; migrated $database to 32 tables; existing data preserved.\n";
    echo 'Backup: '.$backupDirectory."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    if ($maintenanceStarted) {
        fwrite(STDERR, "Application remains in maintenance for inspection; do not run migrate:fresh.\n");
    }
    $failed = true;
} finally {
    DB::purge('schema_restore');
    if ($restoreCreated && preg_match('/^viejob_restore_test_[a-f0-9]{12}$/', $restoreDatabase)) {
        $connection->statement("DROP DATABASE `$restoreDatabase`");
    }
}

exit(isset($failed) ? 1 : 0);
