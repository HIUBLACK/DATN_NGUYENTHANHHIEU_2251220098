<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('normalized_name', 120)->collation('utf8mb4_bin');
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['code'], 'ind_uq_1');
            $table->unique(['normalized_name'], 'ind_uq_2');
            $table->unique(['slug'], 'ind_uq_3');
            $table->index(['is_active', 'sort_order', 'id'], 'ind_idx_1');
        });

        DB::statement('ALTER TABLE `industries` ADD CONSTRAINT `ind_check_1` CHECK (description IS NULL OR CHAR_LENGTH(description) <= 1000)');
        DB::statement('ALTER TABLE `industries` ADD CONSTRAINT `ind_check_2` CHECK (is_active IN (0, 1))');
    }

    public function down(): void
    {
        if (DB::table('industries')->exists()) {
            throw new RuntimeException('Cannot drop non-empty industries; use a reviewed forward migration.');
        }

        Schema::drop('industries');
    }
};
