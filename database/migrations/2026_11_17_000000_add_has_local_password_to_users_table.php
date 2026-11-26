<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_local_password')->nullable()->default(null);
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_has_local_password_check CHECK (has_local_password IS NULL OR has_local_password IN (0, 1))');
        // Chỉ các tài khoản không có Google identity có nguồn gốc mật khẩu rõ ràng.
        DB::table('users')->whereNull('google_subject')->update(['has_local_password' => true]);
    }

    public function down(): void
    {
        // Xóa cờ khi đã có User sẽ làm mất thông tin phân loại mật khẩu.
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Cannot remove password classification while users exist.');
        }
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_has_local_password_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_local_password');
        });
    }
};
