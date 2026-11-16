<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('status', 20)->default('draft');
            $table->string('status_before_block', 20)->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['company_id', 'status', 'created_at', 'id'], 'job_idx_1');
            $table->index(['status', 'published_at', 'id'], 'job_idx_2');
            $table->index(['status', 'expires_at', 'id'], 'job_idx_3');
            $table->foreign(['company_id'], 'job_fk_1')
                ->references(['id'])->on('companies')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `jobs` ADD CONSTRAINT `job_check_1` CHECK (status IN ('draft', 'open', 'paused', 'closed', 'blocked'))");
        DB::statement("ALTER TABLE `jobs` ADD CONSTRAINT `job_check_2` CHECK ((status = 'blocked' AND status_before_block IS NOT NULL AND status_before_block IN ('draft', 'open', 'paused', 'closed')) OR (status <> 'blocked' AND status_before_block IS NULL))");
        DB::statement('ALTER TABLE `jobs` ADD CONSTRAINT `job_check_3` CHECK ((published_version_id IS NULL AND published_at IS NULL AND expires_at IS NULL) OR (published_version_id IS NOT NULL AND published_at IS NOT NULL AND expires_at IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::table('jobs')->exists()) {
            throw new RuntimeException('Cannot drop non-empty jobs; use a reviewed forward migration.');
        }

        Schema::drop('jobs');
    }
};
