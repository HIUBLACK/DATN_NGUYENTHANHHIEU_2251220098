<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_revisions', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('resume_id');
            $table->unsignedInteger('revision');
            $table->string('file_path', 255);
            $table->string('original_filename', 255);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->char('file_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('parsing_status', 20)->default('pending');
            $table->string('extracted_text_path', 255)->nullable();
            $table->string('parser_version', 100)->nullable();
            $table->dateTime('parsed_at', 6)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->char('processing_token', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['resume_id', 'revision'], 'rev_uq_1');
            $table->index(['parsing_status', 'next_retry_at', 'id'], 'rev_idx_1');
            $table->foreign(['resume_id'], 'rev_fk_1')
                ->references(['id'])->on('resumes')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `resume_revisions` ADD CONSTRAINT `rev_check_1` CHECK (revision >= 1)');
        DB::statement('ALTER TABLE `resume_revisions` ADD CONSTRAINT `rev_check_2` CHECK (file_size BETWEEN 1 AND 5242880)');
        DB::statement("ALTER TABLE `resume_revisions` ADD CONSTRAINT `rev_check_3` CHECK (mime_type = 'application/pdf')");
        DB::statement("ALTER TABLE `resume_revisions` ADD CONSTRAINT `rev_check_4` CHECK (parsing_status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE `resume_revisions` ADD CONSTRAINT `rev_check_5` CHECK (parsing_status <> 'completed' OR (parsed_at IS NOT NULL AND extracted_text_path IS NOT NULL))");
    }

    public function down(): void
    {
        if (DB::table('resume_revisions')->exists()) {
            throw new RuntimeException('Cannot drop non-empty resume_revisions; use a reviewed forward migration.');
        }

        Schema::drop('resume_revisions');
    }
};
