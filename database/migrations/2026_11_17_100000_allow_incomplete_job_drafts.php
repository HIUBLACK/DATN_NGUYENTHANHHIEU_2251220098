<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $columns = [
        'position' => 'varchar(100)',
        'description' => 'text',
        'requirements' => 'text',
        'benefits' => 'text',
        'salary_min' => 'decimal(15,2)',
        'salary_max' => 'decimal(15,2)',
        'location' => 'varchar(150)',
        'work_mode' => 'varchar(10)',
        'employment_type' => 'varchar(20)',
        'quantity' => 'smallint(5) unsigned',
        'experience_summary' => 'varchar(100)',
        'job_specialization_id' => 'bigint(20) unsigned',
        'application_deadline' => 'date',
    ];

    public function up(): void
    {
        $this->assertCompatibleData();
        $this->alter(true, $this->checks(true));
    }

    public function assertCompatibleData(): void
    {
        $checks = $this->checks(true);
        foreach ($checks as $expression) {
            if (DB::table('job_versions')->whereRaw('NOT ('.$expression.')')->exists()) {
                throw new RuntimeException('Existing job versions do not meet the approved draft constraints. No data was changed.');
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->columns) as $column) {
            if (DB::table('job_versions')->whereNull($column)->exists()) {
                throw new RuntimeException('Cannot restore NOT NULL while incomplete job drafts exist.');
            }
        }
        $this->alter(false, $this->checks(false));
    }

    private function alter(bool $nullable, array $checks): void
    {
        $actual = collect(DB::select('SHOW COLUMNS FROM job_versions'))->keyBy('Field');
        // MariaDB yêu cầu dựng lại FK khi đổi nullable về NOT NULL; định nghĩa FK không thay đổi.
        $foreignKey = DB::selectOne("SELECT REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS parent_column
            FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'job_versions' AND CONSTRAINT_NAME = 'jv_fk_3' AND COLUMN_NAME = 'job_specialization_id'");
        if ($foreignKey?->parent_table !== 'job_specializations' || $foreignKey?->parent_column !== 'id') {
            throw new RuntimeException('Unexpected specialization foreign key.');
        }
        $clauses = [];
        foreach ($this->columns as $column => $type) {
            if (strtolower($actual[$column]->Type ?? '') !== $type) {
                throw new RuntimeException('Unexpected job_versions column type: '.$column);
            }
            $clauses[] = 'MODIFY COLUMN `'.$column.'` '.$type.($nullable ? ' NULL' : ' NOT NULL');
        }
        foreach ($checks as $name => $expression) {
            $clauses[] = 'DROP CONSTRAINT `'.$name.'`';
            $clauses[] = 'ADD CONSTRAINT `'.$name.'` CHECK ('.$expression.')';
        }
        if ($nullable) {
            DB::statement('ALTER TABLE `job_versions` '.implode(', ', $clauses));
        } else {
            // Chỉ rollback cần tháo/dựng lại FK; phải chạy khi ngừng ghi. MariaDB không cho cùng tên trong một ALTER COPY.
            DB::statement('ALTER TABLE `job_versions` DROP FOREIGN KEY `jv_fk_3`');
            try {
                DB::statement('ALTER TABLE `job_versions` '.implode(', ', $clauses));
            } finally {
                DB::statement('ALTER TABLE `job_versions` ADD CONSTRAINT `jv_fk_3` FOREIGN KEY (`job_specialization_id`) REFERENCES `job_specializations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
            }
        }
    }

    private function checks(bool $nullable): array
    {
        $complete = array_map(fn ($column) => '`'.$column.'` IS NOT NULL', array_keys($this->columns));
        foreach (['position', 'description', 'requirements', 'benefits', 'location', 'experience_summary'] as $column) {
            $complete[] = 'CHAR_LENGTH(TRIM(`'.$column.'`)) > 0';
        }
        $complete[] = 'industry_id IS NOT NULL AND job_level_id IS NOT NULL AND submitted_at IS NOT NULL';

        return [
            'jv_check_2' => ($nullable ? 'quantity IS NULL OR ' : '').'quantity BETWEEN 1 AND 1000',
            'jv_check_3' => $nullable
                ? '(salary_min IS NULL OR salary_min >= 0) AND (salary_max IS NULL OR salary_max >= 0) AND (salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min)'
                : 'salary_min >= 0 AND salary_max >= salary_min',
            'jv_check_8' => ($nullable ? 'work_mode IS NULL OR ' : '')."work_mode IN ('onsite', 'remote', 'hybrid')",
            'jv_check_9' => ($nullable ? 'employment_type IS NULL OR ' : '')."employment_type IN ('full_time', 'part_time', 'internship')",
            'jv_check_11' => $nullable
                ? "CHAR_LENGTH(TRIM(title)) > 0 AND title REGEXP '[^[:space:]]' AND (review_status = 'draft' OR (".implode(' AND ', $complete).'))'
                : "review_status = 'draft' OR (industry_id IS NOT NULL AND job_level_id IS NOT NULL AND submitted_at IS NOT NULL)",
            'jv_check_14' => ($nullable ? 'description IS NULL OR ' : '').'CHAR_LENGTH(description) <= 10000',
            'jv_check_15' => ($nullable ? 'requirements IS NULL OR ' : '').'CHAR_LENGTH(requirements) <= 10000',
            'jv_check_16' => ($nullable ? 'benefits IS NULL OR ' : '').'CHAR_LENGTH(benefits) <= 10000',
        ];
    }
};
