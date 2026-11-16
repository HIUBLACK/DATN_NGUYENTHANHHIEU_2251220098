<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->char('public_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('candidate_id');
            $table->string('display_name', 100);
            $table->unsignedInteger('current_revision');
            $table->boolean('is_primary')->default(0);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unsignedTinyInteger('primary_slot')->storedAs('CASE WHEN is_primary = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['public_id'], 'cv_uq_1');
            $table->unique(['id', 'candidate_id'], 'cv_uq_2');
            $table->unique(['candidate_id', 'primary_slot'], 'cv_uq_3');
            $table->index(['candidate_id', 'deleted_at', 'id'], 'cv_idx_1');
            $table->foreign(['candidate_id'], 'cv_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `resumes` ADD CONSTRAINT `cv_check_1` CHECK (current_revision >= 1)');
        DB::statement('ALTER TABLE `resumes` ADD CONSTRAINT `cv_check_2` CHECK (deleted_at IS NULL OR is_primary = 0)');
        DB::statement('ALTER TABLE `resumes` ADD CONSTRAINT `cv_check_3` CHECK (is_primary IN (0, 1))');
    }

    public function down(): void
    {
        if (DB::table('resumes')->exists()) {
            throw new RuntimeException('Cannot drop non-empty resumes; use a reviewed forward migration.');
        }

        Schema::drop('resumes');
    }
};
