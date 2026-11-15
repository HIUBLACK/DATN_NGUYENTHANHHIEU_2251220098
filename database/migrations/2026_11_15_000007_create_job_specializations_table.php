<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_specializations', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('code', 20);
            $table->unsignedBigInteger('job_category_id');
            $table->string('name', 120);
            $table->string('normalized_name', 120)->collation('utf8mb4_bin');
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['code'], 'spec_uq_1');
            $table->unique(['job_category_id', 'normalized_name'], 'spec_uq_2');
            $table->unique(['slug'], 'spec_uq_3');
            $table->index(['job_category_id', 'is_active', 'sort_order', 'id'], 'spec_idx_1');
            $table->foreign(['job_category_id'], 'spec_fk_1')
                ->references(['id'])->on('job_categories')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `job_specializations` ADD CONSTRAINT `spec_check_1` CHECK (description IS NULL OR CHAR_LENGTH(description) <= 1000)');
        DB::statement('ALTER TABLE `job_specializations` ADD CONSTRAINT `spec_check_2` CHECK (is_active IN (0, 1))');
    }

    public function down(): void
    {
        if (DB::table('job_specializations')->exists()) {
            throw new RuntimeException('Cannot drop non-empty job_specializations; use a reviewed forward migration.');
        }

        Schema::drop('job_specializations');
    }
};
