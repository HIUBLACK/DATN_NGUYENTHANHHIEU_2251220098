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
        $definitions[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition);
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
    if (schemaDefinitions($testConnection) !== $before) {
        throw new RuntimeException('Live schema differs from migrations; report drift before testing models.');
    }
    $process = new Process([PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '--configuration', 'tests/Models/phpunit.xml'], base_path(), [
        'APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $database, 'DB_HOST' => $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
        'DB_USERNAME' => $configuration['username'], 'DB_PASSWORD' => (string) $configuration['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    ]);
    $process->setTimeout(120);
    $process->run(function (string $type, string $output): void {
        echo $output;
    });
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Model test suite failed.');
    }
    if (schemaDefinitions($original) !== $before || $original->table('migrations')->orderBy('id')->get()->toJson() !== $migrationsBefore) {
        throw new RuntimeException('Original schema/history changed during test run.');
    }
    echo "PASS: model tests used an isolated MariaDB database; live schema matches migrations and remains unchanged.\n";
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
