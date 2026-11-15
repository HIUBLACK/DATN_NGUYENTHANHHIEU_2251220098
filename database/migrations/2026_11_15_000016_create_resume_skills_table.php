<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_skills', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('resume_id');
            $table->unsignedInteger('resume_revision');
            $table->unsignedBigInteger('skill_id');
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->string('source', 30)->default('parser');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['resume_id', 'resume_revision', 'skill_id'], 'rs_uq_1');
            $table->index(['skill_id', 'resume_id'], 'rs_idx_1');
            $table->foreign(['resume_id', 'resume_revision'], 'rs_fk_1')
                ->references(['resume_id', 'revision'])->on('resume_revisions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['skill_id'], 'rs_fk_2')
                ->references(['id'])->on('skills')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `resume_skills` ADD CONSTRAINT `rs_check_1` CHECK (confidence_score IS NULL OR confidence_score BETWEEN 0 AND 1)');
        DB::statement("ALTER TABLE `resume_skills` ADD CONSTRAINT `rs_check_2` CHECK (source IN ('parser', 'dictionary', 'model'))");
    }

    public function down(): void
    {
        if (DB::table('resume_skills')->exists()) {
            throw new RuntimeException('Cannot drop non-empty resume_skills; use a reviewed forward migration.');
        }

        Schema::drop('resume_skills');
    }
};
