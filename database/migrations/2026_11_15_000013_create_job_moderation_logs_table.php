<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_moderation_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('job_version_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('action', 10);
            $table->text('reason')->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['job_version_id'], 'jml_uq_1');
            $table->index(['job_id', 'created_at', 'id'], 'jml_idx_1');
            $table->index(['job_id', 'job_version_id'], 'jml_idx_2');
            $table->index(['admin_id'], 'jml_idx_3');
            $table->foreign(['job_id', 'job_version_id'], 'jml_fk_1')
                ->references(['job_id', 'id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['admin_id'], 'jml_fk_2')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `job_moderation_logs` ADD CONSTRAINT `jml_check_1` CHECK (action IN ('approve', 'reject'))");
        DB::statement("ALTER TABLE `job_moderation_logs` ADD CONSTRAINT `jml_check_2` CHECK (action <> 'reject' OR (reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0))");
        DB::statement('ALTER TABLE `job_moderation_logs` ADD CONSTRAINT `jml_check_3` CHECK (reason IS NULL OR CHAR_LENGTH(reason) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('job_moderation_logs')->exists()) {
            throw new RuntimeException('Cannot drop non-empty job_moderation_logs; use a reviewed forward migration.');
        }

        Schema::drop('job_moderation_logs');
    }
};
