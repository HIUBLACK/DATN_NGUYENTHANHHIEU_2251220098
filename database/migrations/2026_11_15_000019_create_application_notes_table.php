<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_notes', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('recruiter_id');
            $table->text('content');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['application_id', 'created_at', 'id'], 'note_idx_1');
            $table->index(['recruiter_id'], 'note_idx_2');
            $table->foreign(['application_id'], 'note_fk_1')
                ->references(['id'])->on('applications')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['recruiter_id'], 'note_fk_2')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `application_notes` ADD CONSTRAINT `note_check_1` CHECK (content IS NULL OR CHAR_LENGTH(content) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('application_notes')->exists()) {
            throw new RuntimeException('Cannot drop non-empty application_notes; use a reviewed forward migration.');
        }

        Schema::drop('application_notes');
    }
};
