<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
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
            $table->unique(['code'], 'pos_uq_1');
            $table->unique(['normalized_name'], 'pos_uq_2');
            $table->unique(['slug'], 'pos_uq_3');
            $table->index(['is_active', 'sort_order', 'id'], 'pos_idx_1');
        });

        DB::statement('ALTER TABLE `job_positions` ADD CONSTRAINT `pos_check_1` CHECK (description IS NULL OR CHAR_LENGTH(description) <= 1000)');
        DB::statement('ALTER TABLE `job_positions` ADD CONSTRAINT `pos_check_2` CHECK (is_active IN (0, 1))');
        Schema::table('job_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('job_position_id')->nullable()->index('jv_position_idx');
            $table->string('province_code', 2)->nullable()->index('jv_province_idx');
            $table->foreign('job_position_id', 'jv_position_fk')->references('id')->on('job_positions')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE job_versions MODIFY position VARCHAR(120) NULL');
        $this->replaceCompletenessCheck(false);
    }

    public function down(): void
    {
        if (DB::table('job_positions')->exists()
            || DB::table('job_versions')->whereNotNull('job_position_id')->orWhereNotNull('province_code')->exists()
            || DB::table('job_versions')->whereRaw('CHAR_LENGTH(position) > 100')->exists()) {
            throw new RuntimeException('Cannot drop non-empty job_positions; use a reviewed forward migration.');
        }

        $this->replaceCompletenessCheck(true);
        Schema::table('job_versions', function (Blueprint $table) {
            $table->dropForeign('jv_position_fk');
            $table->dropColumn(['job_position_id', 'province_code']);
        });
        DB::statement('ALTER TABLE job_versions MODIFY position VARCHAR(100) NULL');
        Schema::drop('job_positions');
    }

    private function replaceCompletenessCheck(bool $withExperience): void
    {
        $columns = ['position', 'description', 'requirements', 'benefits', 'salary_min', 'salary_max',
            'location', 'work_mode', 'employment_type', 'quantity', 'job_specialization_id', 'application_deadline'];
        $textColumns = ['position', 'description', 'requirements', 'benefits', 'location'];
        if ($withExperience) {
            array_splice($columns, 10, 0, ['experience_summary']);
            $textColumns[] = 'experience_summary';
        }
        $complete = array_map(fn ($column) => "`$column` IS NOT NULL", $columns);
        foreach ($textColumns as $column) {
            $complete[] = "CHAR_LENGTH(TRIM(`$column`)) > 0";
        }
        $complete[] = 'industry_id IS NOT NULL AND job_level_id IS NOT NULL AND submitted_at IS NOT NULL';
        $expression = "CHAR_LENGTH(TRIM(title)) > 0 AND title REGEXP '[^[:space:]]' AND (review_status = 'draft' OR (".implode(' AND ', $complete).'))';
        if (DB::table('job_versions')->whereRaw('NOT ('.$expression.')')->exists()) {
            throw new RuntimeException('Existing versions prevent restoring the completeness constraint.');
        }
        DB::statement('ALTER TABLE job_versions DROP CONSTRAINT jv_check_11, ADD CONSTRAINT jv_check_11 CHECK ('.$expression.')');
    }
};
