<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOrphanSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->exists();

        if ($hasOrphanSessions) {
            throw new RuntimeException('Resolve orphan sessions before adding their foreign key.');
        }

        // Both tables must exist before introducing this circular reference.
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['id', 'published_version_id'], 'job_published_idx');
            $table->foreign(['id', 'published_version_id'], 'job_published_fk')
                ->references(['job_id', 'id'])->on('job_versions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id', 'sessions_user_fk')->references('id')->on('users')
                ->nullOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        if (DB::table('jobs')->exists()) {
            throw new RuntimeException('Cannot remove publication integrity constraints from existing jobs.');
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign('sessions_user_fk');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign('job_published_fk');
            $table->dropIndex('job_published_idx');
        });
    }
};
