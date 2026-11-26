<?php

use App\Support\JobCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const TABLES = [
        'job_specializations' => 'specializations',
        'industries' => 'industries',
        'job_categories' => 'categories',
        'skills' => 'skills',
        'job_levels' => 'levels',
        'job_positions' => 'positions',
    ];

    public function up(): void
    {
        $backup = [];
        foreach (self::TABLES as $table => $group) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)->orderBy('id')->get();
            foreach ($rows as $row) {
                $option = JobCatalog::find($group, $row->id);
                // Dừng trước DDL nếu danh mục thay đổi sau khảo sát; không ánh xạ nhầm dữ liệu cũ.
                if (! $option || $option->name !== $row->name || ! $row->is_active
                    || ($table === 'job_specializations' && $option->job_category_id !== $row->job_category_id)) {
                    throw new RuntimeException('Cần đối chiếu lại danh mục '.$table.' ID '.$row->id.' trước khi chuyển đổi.');
                }
            }
            $backup[$table] = [
                'create_sql' => array_values((array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`'))[1],
                'rows' => $rows->all(),
            ];
        }

        $references = DB::select(
            'SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN (?,?,?,?,?,?)',
            array_keys(self::TABLES)
        );
        $allowedReferences = ['companies', 'job_versions', 'job_version_skills', 'resume_skills', 'job_specializations'];
        foreach ($references as $reference) {
            if (! in_array($reference->TABLE_NAME, $allowedReferences, true)) {
                throw new RuntimeException('Có khóa ngoại ngoài phạm vi khảo sát; chưa xóa danh mục.');
            }
        }
        $backup['foreign_keys'] = $references;
        foreach (['companies', 'job_versions', 'job_version_skills', 'resume_skills'] as $table) {
            $backup['referencing_schema'][$table] = array_values((array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
        }
        // Sao lưu riêng trước khi xóa. Không tự phục hồi vì MariaDB DDL không rollback giao dịch.
        $path = 'catalog-backups/'.DB::connection()->getDatabaseName().'-'.now()->format('Ymd-His-u').'.json';
        if (! Storage::disk('local')->put($path, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Không tạo được bản sao lưu danh mục; chưa xóa bảng.');
        }
        foreach ($references as $reference) {
            $table = $reference->TABLE_NAME;
            $constraint = $reference->CONSTRAINT_NAME;
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table.$constraint)) {
                throw new RuntimeException('Tên ràng buộc không hợp lệ.');
            }
            DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$constraint.'`');
        }
        foreach (array_keys(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Phục hồi danh mục cần bản sao lưu tương ứng và đối chiếu mã mới; không rollback tự động.');
    }
};
