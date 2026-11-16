<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_versions', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedInteger('version_number');
            $table->string('title', 150);
            $table->string('position', 100);
            $table->text('description');
            $table->text('requirements');
            $table->text('benefits');
            $table->decimal('salary_min', 15, 2);
            $table->decimal('salary_max', 15, 2);
            $table->char('salary_currency', 3)->charset('ascii')->collation('ascii_bin')->default('VND');
            $table->boolean('salary_negotiable')->default(0);
            $table->string('location', 150);
            $table->string('work_mode', 10);
            $table->string('employment_type', 20);
            $table->unsignedSmallInteger('quantity');
            $table->string('experience_summary', 100);
            $table->decimal('experience_min', 4, 1)->nullable();
            $table->decimal('experience_max', 4, 1)->nullable();
            $table->unsignedBigInteger('industry_id')->nullable();
            $table->unsignedBigInteger('job_specialization_id');
            $table->unsignedBigInteger('job_level_id')->nullable();
            $table->date('application_deadline');
            $table->string('review_status', 20)->default('draft');
            $table->dateTime('submitted_at', 6)->nullable();
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->unsignedTinyInteger('pending_slot')->storedAs("CASE WHEN review_status = 'pending' THEN 1 ELSE NULL END");
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['job_id', 'version_number'], 'jv_uq_1');
            $table->unique(['job_id', 'id'], 'jv_uq_2');
            $table->unique(['job_id', 'pending_slot'], 'jv_uq_3');
            $table->index(['review_status', 'submitted_at', 'id'], 'jv_idx_1');
            $table->index(['job_specialization_id', 'id'], 'jv_idx_2');
            $table->index(['industry_id', 'id'], 'jv_idx_3');
            $table->index(['job_level_id', 'id'], 'jv_idx_4');
            $table->index(['location', 'work_mode', 'id'], 'jv_idx_5');
            $table->foreign(['job_id'], 'jv_fk_1')
                ->references(['id'])->on('jobs')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['industry_id'], 'jv_fk_2')
                ->references(['id'])->on('industries')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_specialization_id'], 'jv_fk_3')
                ->references(['id'])->on('job_specializations')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_level_id'], 'jv_fk_4')
                ->references(['id'])->on('job_levels')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_1` CHECK (version_number >= 1)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_2` CHECK (quantity BETWEEN 1 AND 1000)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_3` CHECK (salary_min >= 0 AND salary_max >= salary_min)');
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_4` CHECK (salary_currency = 'VND')");
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_5` CHECK (experience_min IS NULL OR experience_min >= 0)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_6` CHECK (experience_max IS NULL OR experience_max >= 0)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_7` CHECK (experience_min IS NULL OR experience_max IS NULL OR experience_min <= experience_max)');
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_8` CHECK (work_mode IN ('onsite', 'remote', 'hybrid'))");
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_9` CHECK (employment_type IN ('full_time', 'part_time', 'internship'))");
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_10` CHECK (review_status IN ('draft', 'pending', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_11` CHECK (review_status = 'draft' OR (industry_id IS NOT NULL AND job_level_id IS NOT NULL AND submitted_at IS NOT NULL))");
        DB::statement("ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_12` CHECK ((review_status IN ('approved', 'rejected') AND reviewed_at IS NOT NULL) OR (review_status IN ('draft', 'pending') AND reviewed_at IS NULL))");
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_13` CHECK (salary_negotiable IN (0, 1))');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_14` CHECK (CHAR_LENGTH(description) <= 10000)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_15` CHECK (CHAR_LENGTH(requirements) <= 10000)');
        DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_check_16` CHECK (CHAR_LENGTH(benefits) <= 10000)');
    }

    public function down(): void
    {
        if (DB::table('job_versions')->exists()) {
            throw new RuntimeException('Cannot drop non-empty job_versions; use a reviewed forward migration.');
        }

        Schema::drop('job_versions');
    }
};
