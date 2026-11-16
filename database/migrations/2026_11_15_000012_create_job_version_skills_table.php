<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_version_skills', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('job_version_id');
            $table->unsignedBigInteger('skill_id');
            $table->boolean('is_required')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['job_version_id', 'skill_id'], 'jvs_uq_1');
            $table->index(['skill_id', 'job_version_id'], 'jvs_idx_1');
            $table->foreign(['job_version_id'], 'jvs_fk_1')
                ->references(['id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['skill_id'], 'jvs_fk_2')
                ->references(['id'])->on('skills')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `job_version_skills` ADD CONSTRAINT `jvs_check_1` CHECK (is_required IN (0, 1))');
    }

    public function down(): void
    {
        if (DB::table('job_version_skills')->exists()) {
            throw new RuntimeException('Cannot drop non-empty job_version_skills; use a reviewed forward migration.');
        }

        Schema::drop('job_version_skills');
    }
};
