<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;

        preg_match('/^\d+\.\d+\.\d+/', $version, $numericVersion);

        if (! str_contains($version, 'MariaDB') || ! version_compare($numericVersion[0] ?? '0', '11.5.2', '>=')) {
            throw new RuntimeException('This schema requires MariaDB 11.5.2 or newer.');
        }

        if (Schema::hasTable('queue_jobs') || ! Schema::hasColumns('jobs', ['queue', 'payload', 'attempts', 'reserved_at'])) {
            throw new RuntimeException('Unexpected queue schema. Stop and inspect before renaming.');
        }

        if (Schema::hasColumn('jobs', 'company_id')) {
            throw new RuntimeException('jobs already contains recruitment data.');
        }

        // Producers and workers must be stopped during the deployment cutover.
        Schema::rename('jobs', 'queue_jobs');
    }

    public function down(): void
    {
        if (Schema::hasTable('jobs')) {
            throw new RuntimeException('Cannot rename queue_jobs while recruitment jobs exists.');
        }

        Schema::rename('queue_jobs', 'jobs');
    }
};
