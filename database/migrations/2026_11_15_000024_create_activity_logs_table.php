<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 40);
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->text('reason')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['subject_type', 'subject_id', 'created_at', 'id'], 'audit_idx_1');
            $table->index(['actor_id', 'created_at', 'id'], 'audit_idx_2');
            $table->index(['action', 'created_at', 'id'], 'audit_idx_3');
            $table->foreign(['actor_id'], 'audit_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_1` CHECK (subject_id > 0)');
        DB::statement("ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_2` CHECK ((subject_type = 'user' AND action IN ('user.lock', 'user.unlock')) OR (subject_type = 'company' AND action IN ('company.lock', 'company.unlock')) OR (subject_type = 'job' AND action IN ('job.create', 'job.open', 'job.pause', 'job.close', 'job.block', 'job.unblock')))");
        DB::statement("ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_3` CHECK (JSON_TYPE(new_values) = 'OBJECT')");
        DB::statement("ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_4` CHECK ((action = 'job.create' AND old_values IS NULL) OR (action <> 'job.create' AND old_values IS NOT NULL AND JSON_TYPE(old_values) = 'OBJECT'))");
        DB::statement("ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_5` CHECK (action NOT IN ('user.lock', 'company.lock', 'job.block') OR (reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0))");
        DB::statement('ALTER TABLE `activity_logs` ADD CONSTRAINT `audit_check_6` CHECK (reason IS NULL OR CHAR_LENGTH(reason) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('activity_logs')->exists()) {
            throw new RuntimeException('Cannot drop non-empty activity_logs; use a reviewed forward migration.');
        }

        Schema::drop('activity_logs');
    }
};
