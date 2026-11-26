<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_revisions', function (Blueprint $table) {
            $table->json('analysis_result')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('resume_revisions')->whereNotNull('analysis_result')->exists()) {
            throw new RuntimeException('Cannot drop populated CV analysis results.');
        }
        Schema::table('resume_revisions', function (Blueprint $table) {
            $table->dropColumn('analysis_result');
        });
    }
};
