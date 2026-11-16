<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_runs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('resume_id');
            $table->unsignedInteger('resume_revision');
            $table->unsignedInteger('recommendation_generation');
            $table->string('model_version', 100);
            $table->string('formula_version', 30);
            $table->char('request_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('status', 20)->default('pending');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('stale_at', 6)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->char('processing_token', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['request_key'], 'rr_uq_1');
            $table->index(['candidate_id', 'status', 'created_at', 'id'], 'rr_idx_1');
            $table->index(['status', 'next_retry_at', 'id'], 'rr_idx_2');
            $table->index(['resume_id', 'candidate_id'], 'rr_idx_3');
            $table->index(['resume_id', 'resume_revision'], 'rr_idx_4');
            $table->foreign(['candidate_id'], 'rr_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['resume_id', 'candidate_id'], 'rr_fk_2')
                ->references(['id', 'candidate_id'])->on('resumes')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['resume_id', 'resume_revision'], 'rr_fk_3')
                ->references(['resume_id', 'revision'])->on('resume_revisions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `recommendation_runs` ADD CONSTRAINT `rr_check_1` CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE `recommendation_runs` ADD CONSTRAINT `rr_check_2` CHECK (status <> 'completed' OR completed_at IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::table('recommendation_runs')->exists()) {
            throw new RuntimeException('Cannot drop non-empty recommendation_runs; use a reviewed forward migration.');
        }

        Schema::drop('recommendation_runs');
    }
};
