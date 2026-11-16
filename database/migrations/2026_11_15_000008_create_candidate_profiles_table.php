<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone', 20)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('professional_title', 100)->nullable();
            $table->string('experience_summary', 100)->nullable();
            $table->text('summary')->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->unsignedInteger('recommendation_generation')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id'], 'cp_uq_1');
            $table->foreign(['user_id'], 'cp_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `candidate_profiles` ADD CONSTRAINT `cp_check_1` CHECK (summary IS NULL OR CHAR_LENGTH(summary) <= 1500)');
    }

    public function down(): void
    {
        if (DB::table('candidate_profiles')->exists()) {
            throw new RuntimeException('Cannot drop non-empty candidate_profiles; use a reviewed forward migration.');
        }

        Schema::drop('candidate_profiles');
    }
};
