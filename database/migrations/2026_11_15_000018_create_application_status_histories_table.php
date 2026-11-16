<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('note')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['application_id', 'created_at', 'id'], 'ash_idx_1');
            $table->index(['actor_id'], 'ash_idx_2');
            $table->foreign(['application_id'], 'ash_fk_1')
                ->references(['id'])->on('applications')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['actor_id'], 'ash_fk_2')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `application_status_histories` ADD CONSTRAINT `ash_check_1` CHECK (new_status IN ('applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn'))");
        DB::statement("ALTER TABLE `application_status_histories` ADD CONSTRAINT `ash_check_2` CHECK (old_status IS NULL OR (old_status IN ('applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn') AND old_status <> new_status))");
        DB::statement("ALTER TABLE `application_status_histories` ADD CONSTRAINT `ash_check_3` CHECK (old_status IS NOT NULL OR new_status = 'applied')");
        DB::statement('ALTER TABLE `application_status_histories` ADD CONSTRAINT `ash_check_4` CHECK (note IS NULL OR CHAR_LENGTH(note) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('application_status_histories')->exists()) {
            throw new RuntimeException('Cannot drop non-empty application_status_histories; use a reviewed forward migration.');
        }

        Schema::drop('application_status_histories');
    }
};
