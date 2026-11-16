<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Never infer roles for existing accounts from demo data.
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Existing users require a reviewed role mapping before this migration.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20);
            $table->string('status', 20)->default('active');
            $table->dateTime('last_login_at', 6)->nullable();
            $table->index(['role', 'status', 'id'], 'users_role_status_idx');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('candidate', 'recruiter', 'admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'locked'))");
    }

    public function down(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Cannot remove roles from existing users.');
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check, DROP CONSTRAINT users_status_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_idx');
            $table->dropColumn(['role', 'status', 'last_login_at']);
        });
    }
};
