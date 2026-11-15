<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('recommendation_run_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('job_version_id');
            $table->decimal('score', 5, 2);
            $table->decimal('semantic_score', 5, 2);
            $table->decimal('skill_score', 5, 2);
            $table->json('matched_skills');
            $table->json('missing_skills');
            $table->text('explanation');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['recommendation_run_id', 'job_id'], 'ri_uq_1');
            $table->index(['recommendation_run_id', 'score', 'id'], 'ri_idx_1');
            $table->index(['job_id', 'job_version_id'], 'ri_idx_2');
            $table->foreign(['recommendation_run_id'], 'ri_fk_1')
                ->references(['id'])->on('recommendation_runs')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_id', 'job_version_id'], 'ri_fk_2')
                ->references(['job_id', 'id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `recommendation_items` ADD CONSTRAINT `ri_check_1` CHECK (score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE `recommendation_items` ADD CONSTRAINT `ri_check_2` CHECK (semantic_score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE `recommendation_items` ADD CONSTRAINT `ri_check_3` CHECK (skill_score BETWEEN 0 AND 100)');
        DB::statement("ALTER TABLE `recommendation_items` ADD CONSTRAINT `ri_check_4` CHECK (JSON_TYPE(matched_skills) = 'ARRAY')");
        DB::statement("ALTER TABLE `recommendation_items` ADD CONSTRAINT `ri_check_5` CHECK (JSON_TYPE(missing_skills) = 'ARRAY')");
    }

    public function down(): void
    {
        if (DB::table('recommendation_items')->exists()) {
            throw new RuntimeException('Cannot drop non-empty recommendation_items; use a reviewed forward migration.');
        }

        Schema::drop('recommendation_items');
    }
};
