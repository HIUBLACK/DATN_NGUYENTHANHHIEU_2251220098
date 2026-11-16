<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->char('public_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('recruiter_id');
            $table->string('name', 150);
            $table->string('short_name', 80)->nullable();
            $table->string('slug', 160);
            $table->string('logo_path', 255)->nullable();
            $table->string('cover_image_path', 255)->nullable();
            $table->json('office_images');
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('company_size', 30)->nullable();
            $table->unsignedBigInteger('industry_id')->nullable();
            $table->string('company_type', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('address', 255);
            $table->string('working_hours', 255)->nullable();
            $table->text('overtime_policy')->nullable();
            $table->text('benefits')->nullable();
            $table->text('technologies')->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['public_id'], 'co_uq_1');
            $table->unique(['slug'], 'co_uq_2');
            $table->unique(['recruiter_id'], 'co_uq_3');
            $table->index(['status', 'name', 'id'], 'co_idx_1');
            $table->index(['industry_id'], 'co_idx_2');
            $table->foreign(['recruiter_id'], 'co_fk_1')
                ->references(['id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['industry_id'], 'co_fk_2')
                ->references(['id'])->on('industries')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `companies` ADD CONSTRAINT `co_check_1` CHECK (status IN ('active', 'locked'))");
        DB::statement('ALTER TABLE `companies` ADD CONSTRAINT `co_check_2` CHECK (founded_year IS NULL OR founded_year >= 1800)');
        DB::statement("ALTER TABLE `companies` ADD CONSTRAINT `co_check_3` CHECK (JSON_TYPE(office_images) = 'ARRAY' AND JSON_LENGTH(office_images) <= 6)");
        DB::statement('ALTER TABLE `companies` ADD CONSTRAINT `co_check_4` CHECK (description IS NULL OR CHAR_LENGTH(description) <= 10000)');
        DB::statement('ALTER TABLE `companies` ADD CONSTRAINT `co_check_5` CHECK (overtime_policy IS NULL OR CHAR_LENGTH(overtime_policy) <= 2000)');
        DB::statement('ALTER TABLE `companies` ADD CONSTRAINT `co_check_6` CHECK (benefits IS NULL OR CHAR_LENGTH(benefits) <= 3000)');
        DB::statement('ALTER TABLE `companies` ADD CONSTRAINT `co_check_7` CHECK (technologies IS NULL OR CHAR_LENGTH(technologies) <= 2000)');
    }

    public function down(): void
    {
        if (DB::table('companies')->exists()) {
            throw new RuntimeException('Cannot drop non-empty companies; use a reviewed forward migration.');
        }

        Schema::drop('companies');
    }
};
