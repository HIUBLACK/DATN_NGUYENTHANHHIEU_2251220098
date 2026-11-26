<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_versions', function (Blueprint $table) {
            $table->json('ai_analysis_result')->nullable();
            $table->string('ai_analysis_status', 20)->nullable();
            $table->char('ai_processing_token', 36)->nullable();
            $table->unsignedSmallInteger('ai_attempt_count')->default(0);
            $table->dateTime('ai_started_at', 6)->nullable();
            $table->dateTime('ai_lease_expires_at', 6)->nullable();
            $table->dateTime('ai_next_retry_at', 6)->nullable();
            $table->dateTime('ai_analyzed_at', 6)->nullable();
            $table->string('ai_model_version', 100)->nullable();
            $table->string('ai_error_code', 60)->nullable();
            $table->index(['ai_analysis_status', 'ai_next_retry_at', 'id'], 'jv_ai_idx');
        });
        DB::statement("ALTER TABLE job_versions ADD CONSTRAINT jv_ai_status_check
            CHECK (ai_analysis_status IS NULL OR ai_analysis_status IN ('pending','processing','completed','failed'))");
        DB::statement('ALTER TABLE job_versions ADD CONSTRAINT jv_ai_attempt_check CHECK (ai_attempt_count <= 3)');
        DB::statement("ALTER TABLE job_versions ADD CONSTRAINT jv_ai_result_check
            CHECK (ai_analysis_status IS NULL OR ai_analysis_status <> 'completed' OR
            (ai_analysis_result IS NOT NULL AND ai_analyzed_at IS NOT NULL AND ai_model_version IS NOT NULL))");
    }

    public function down(): void
    {
        // Không rollback làm mất kết quả phân tích đã được lưu.
        if (DB::table('job_versions')->whereNotNull('ai_analysis_result')->exists()) {
            throw new RuntimeException('Cannot drop populated job analysis results.');
        }
        foreach (['jv_ai_status_check', 'jv_ai_attempt_check', 'jv_ai_result_check'] as $constraint) {
            DB::statement("ALTER TABLE job_versions DROP CONSTRAINT $constraint");
        }
        Schema::table('job_versions', function (Blueprint $table) {
            $table->dropIndex('jv_ai_idx');
            $table->dropColumn([
                'ai_analysis_result', 'ai_analysis_status', 'ai_processing_token', 'ai_attempt_count',
                'ai_started_at', 'ai_lease_expires_at', 'ai_next_retry_at', 'ai_analyzed_at',
                'ai_model_version', 'ai_error_code',
            ]);
        });
    }
};
