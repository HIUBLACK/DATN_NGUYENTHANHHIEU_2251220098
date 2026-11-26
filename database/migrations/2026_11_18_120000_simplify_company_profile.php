<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dữ liệu sáu cột cũ phải được sao lưu trước khi chạy migration đã duyệt này.
        DB::statement('ALTER TABLE companies
            DROP CONSTRAINT co_check_2, DROP CONSTRAINT co_check_6, DROP CONSTRAINT co_check_7,
            DROP COLUMN short_name, DROP COLUMN short_description, DROP COLUMN founded_year,
            DROP COLUMN benefits, DROP COLUMN technologies, DROP COLUMN cover_image_path,
            ADD COLUMN work_environment TEXT NULL AFTER overtime_policy,
            ADD CONSTRAINT co_work_environment_check CHECK (work_environment IS NULL OR CHAR_LENGTH(work_environment) <= 3000)');
    }

    public function down(): void
    {
        if (DB::table('companies')->whereNotNull('work_environment')->exists()) {
            throw new RuntimeException('Cannot rollback while work environment content exists. Restore from a verified backup instead.');
        }
        // Down chỉ phục hồi cấu trúc. Nội dung cột đã bỏ chỉ có trong backup, không tự suy diễn.
        DB::statement('ALTER TABLE companies
            DROP CONSTRAINT co_work_environment_check, DROP COLUMN work_environment,
            ADD COLUMN short_name VARCHAR(80) NULL AFTER name,
            ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER logo_path,
            ADD COLUMN short_description VARCHAR(500) NULL AFTER office_images,
            ADD COLUMN founded_year SMALLINT UNSIGNED NULL AFTER description,
            ADD COLUMN benefits TEXT NULL AFTER overtime_policy,
            ADD COLUMN technologies TEXT NULL AFTER benefits,
            ADD CONSTRAINT co_check_2 CHECK (founded_year IS NULL OR founded_year >= 1800),
            ADD CONSTRAINT co_check_6 CHECK (benefits IS NULL OR CHAR_LENGTH(benefits) <= 3000),
            ADD CONSTRAINT co_check_7 CHECK (technologies IS NULL OR CHAR_LENGTH(technologies) <= 2000)');
    }
};
