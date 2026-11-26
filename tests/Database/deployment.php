<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$originalName = config('database.default');
$original = DB::connection();
$configuration = config('database.connections.'.$originalName);
$sourceTables = $original->select('SHOW TABLES');
$sourceMigrations = $original->table('migrations')->orderBy('id')->get()->toJson();
$database = 'viejob_deployment_test_'.bin2hex(random_bytes(6));
$configuration['database'] = $database;
$configuration['url'] = null;
unset($configuration['name']);
config(['database.connections.deployment_test' => $configuration]);
$original->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$testConnection = DB::connection('deployment_test');

try {
    DB::setDefaultConnection('deployment_test');
    $baseline = glob(__DIR__.'/../../database/migrations/0001_*.php');
    if (Artisan::call('migrate', ['--database' => 'deployment_test', '--path' => $baseline, '--realpath' => true, '--force' => true]) !== 0) {
        throw new RuntimeException('Cannot prepare isolated baseline.');
    }
    $testConnection->table('jobs')->insert([
        'queue' => 'deployment-test', 'payload' => '{"sentinel":true}', 'attempts' => 2,
        'reserved_at' => null, 'available_at' => 123, 'created_at' => 123,
    ]);
    $process = new Process([PHP_BINARY, 'database/deploy-schema.php', '--apply'], base_path(), [
        'APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $database, 'DB_HOST' => $configuration['host'],
        'DB_PORT' => (string) $configuration['port'], 'DB_USERNAME' => $configuration['username'],
        'DB_PASSWORD' => (string) $configuration['password'], 'DB_QUEUE_TABLE' => 'queue_jobs',
    ]);
    $process->setTimeout(120);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput());
    }
    if (count($testConnection->select('SHOW TABLES')) !== 27 || $testConnection->table('migrations')->count() !== count(glob(database_path('migrations/*.php')))) {
        throw new RuntimeException('Deployment did not migrate isolated target.');
    }
    if ($testConnection->table('queue_jobs')->first()->payload !== '{"sentinel":true}') {
        throw new RuntimeException('Queue sentinel changed.');
    }
    if (json_encode($original->select('SHOW TABLES')) !== json_encode($sourceTables) || $original->table('migrations')->orderBy('id')->get()->toJson() !== $sourceMigrations) {
        throw new RuntimeException('Deployment modified the original database.');
    }
    echo "PASS: backup/restore/deployment targets isolated database; source tables and migration history unchanged.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $failed = true;
} finally {
    DB::setDefaultConnection($originalName);
    DB::purge('deployment_test');
    if (preg_match('/^viejob_deployment_test_[a-f0-9]{12}$/', $database)) {
        $original->statement("DROP DATABASE `$database`");
    }
}

exit(isset($failed) ? 1 : 0);
