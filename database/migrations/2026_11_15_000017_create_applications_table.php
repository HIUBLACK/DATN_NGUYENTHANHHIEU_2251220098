<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->char('public_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedTinyInteger('attempt_no');
            $table->unsignedBigInteger('resume_id');
            $table->unsignedInteger('resume_revision');
            $table->string('resume_snapshot_path', 255);
            $table->char('resume_snapshot_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('resume_snapshot_filename', 255);
            $table->unsignedInteger('resume_snapshot_size');
            $table->unsignedBigInteger('job_version_id');
            $table->text('cover_letter')->nullable();
            $table->string('status', 20)->default('applied');
            $table->dateTime('applied_at', 6);
            $table->dateTime('withdrawn_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['public_id'], 'app_uq_1');
            $table->unique(['candidate_id', 'job_id', 'attempt_no'], 'app_uq_2');
            $table->unique(['id', 'job_id'], 'app_uq_3');
            $table->index(['job_id', 'status', 'applied_at', 'id'], 'app_idx_1');
            $table->index(['candidate_id', 'status', 'applied_at', 'id'], 'app_idx_2');
            $table->index(['resume_id', 'candidate_id'], 'app_idx_3');
            $table->index(['resume_id', 'resume_revision'], 'app_idx_4');
            $table->index(['job_id', 'job_version_id'], 'app_idx_5');
            $table->foreign(['candidate_id'], 'app_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['resume_id', 'candidate_id'], 'app_fk_2')
                ->references(['id', 'candidate_id'])->on('resumes')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['resume_id', 'resume_revision'], 'app_fk_3')
                ->references(['resume_id', 'revision'])->on('resume_revisions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_id', 'job_version_id'], 'app_fk_4')
                ->references(['job_id', 'id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `applications` ADD CONSTRAINT `app_check_1` CHECK (attempt_no IN (1, 2))');
        DB::statement('ALTER TABLE `applications` ADD CONSTRAINT `app_check_2` CHECK (resume_snapshot_size BETWEEN 1 AND 5242880)');
        DB::statement("ALTER TABLE `applications` ADD CONSTRAINT `app_check_3` CHECK (status IN ('applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn'))");
        DB::statement("ALTER TABLE `applications` ADD CONSTRAINT `app_check_4` CHECK ((status = 'withdrawn' AND withdrawn_at IS NOT NULL) OR (status <> 'withdrawn' AND withdrawn_at IS NULL))");
        DB::statement('ALTER TABLE `applications` ADD CONSTRAINT `app_check_5` CHECK (cover_letter IS NULL OR CHAR_LENGTH(cover_letter) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('applications')->exists()) {
            throw new RuntimeException('Cannot drop non-empty applications; use a reviewed forward migration.');
        }

        Schema::drop('applications');
    }
};
