<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_ai_evaluations', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('job_version_id');
            $table->char('resume_snapshot_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('model_version', 100);
            $table->string('formula_version', 30);
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('semantic_score', 5, 2)->nullable();
            $table->decimal('skill_score', 5, 2)->nullable();
            $table->json('matched_skills')->nullable();
            $table->json('missing_skills')->nullable();
            $table->text('explanation')->nullable();
            $table->string('evaluation_status', 20)->default('pending');
            $table->dateTime('evaluated_at', 6)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->char('processing_token', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['application_id', 'job_version_id', 'model_version', 'formula_version'], 'ai_uq_1');
            $table->index(['job_id', 'job_version_id', 'evaluation_status', 'score', 'id'], 'ai_idx_1');
            $table->index(['evaluation_status', 'next_retry_at', 'id'], 'ai_idx_2');
            $table->index(['application_id', 'job_id'], 'ai_idx_3');
            $table->foreign(['application_id', 'job_id'], 'ai_fk_1')
                ->references(['id', 'job_id'])->on('applications')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_id', 'job_version_id'], 'ai_fk_2')
                ->references(['job_id', 'id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_1` CHECK (evaluation_status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_2` CHECK (evaluation_status <> 'completed' OR (score IS NOT NULL AND semantic_score IS NOT NULL AND skill_score IS NOT NULL AND matched_skills IS NOT NULL AND missing_skills IS NOT NULL AND explanation IS NOT NULL AND evaluated_at IS NOT NULL))");
        DB::statement('ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_3` CHECK (score IS NULL OR score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_4` CHECK (semantic_score IS NULL OR semantic_score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_5` CHECK (skill_score IS NULL OR skill_score BETWEEN 0 AND 100)');
        DB::statement("ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_6` CHECK (matched_skills IS NULL OR JSON_TYPE(matched_skills) = 'ARRAY')");
        DB::statement("ALTER TABLE `application_ai_evaluations` ADD CONSTRAINT `ai_check_7` CHECK (missing_skills IS NULL OR JSON_TYPE(missing_skills) = 'ARRAY')");
    }

    public function down(): void
    {
        if (DB::table('application_ai_evaluations')->exists()) {
            throw new RuntimeException('Cannot drop non-empty application_ai_evaluations; use a reviewed forward migration.');
        }

        Schema::drop('application_ai_evaluations');
    }
};
