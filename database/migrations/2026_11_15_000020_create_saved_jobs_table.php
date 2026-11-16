<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('job_id');
            $table->dateTime('created_at', 6);
            $table->unique(['candidate_id', 'job_id'], 'saved_uq_1');
            $table->index(['candidate_id', 'created_at', 'id'], 'saved_idx_1');
            $table->index(['job_id'], 'saved_idx_2');
            $table->foreign(['candidate_id'], 'saved_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['job_id'], 'saved_fk_2')
                ->references(['id'])->on('jobs')
                ->restrictOnDelete()->restrictOnUpdate();
        });

    }

    public function down(): void
    {
        if (DB::table('saved_jobs')->exists()) {
            throw new RuntimeException('Cannot drop non-empty saved_jobs; use a reviewed forward migration.');
        }

        Schema::drop('saved_jobs');
    }
};
