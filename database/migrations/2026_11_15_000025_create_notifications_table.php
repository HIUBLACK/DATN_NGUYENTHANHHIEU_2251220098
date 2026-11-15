<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->char('id', 36)->charset('ascii')->collation('ascii_bin')->primary();
            $table->string('type', 255);
            $table->string('notifiable_type', 255);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id'], 'nt_idx_1');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'nt_idx_2');
        });

        DB::statement('ALTER TABLE `notifications` ADD CONSTRAINT `nt_check_1` CHECK (JSON_VALID(data))');
    }

    public function down(): void
    {
        if (DB::table('notifications')->exists()) {
            throw new RuntimeException('Cannot drop non-empty notifications; use a reviewed forward migration.');
        }

        Schema::drop('notifications');
    }
};
