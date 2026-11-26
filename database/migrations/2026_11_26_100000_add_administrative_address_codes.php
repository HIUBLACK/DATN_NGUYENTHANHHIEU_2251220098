<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chỉ thêm cột đã được duyệt; không suy diễn hoặc backfill địa chỉ cũ.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('province_code', 2)->nullable();
            $table->string('ward_code', 5)->nullable();
        });
        Schema::table('job_versions', function (Blueprint $table) {
            $table->string('ward_code', 5)->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('companies')->whereNotNull('province_code')->orWhereNotNull('ward_code')->exists()
            || \Illuminate\Support\Facades\DB::table('job_versions')->whereNotNull('ward_code')->exists()) {
            throw new RuntimeException('Không bỏ cột địa chỉ đang có dữ liệu.');
        }
        Schema::table('job_versions', fn (Blueprint $table) => $table->dropColumn('ward_code'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['province_code', 'ward_code']));
    }
};
