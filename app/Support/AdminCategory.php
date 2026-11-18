<?php

namespace App\Support;

use App\Models\Industry;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobSpecialization;
use App\Models\Skill;
use Normalizer;

class AdminCategory
{
    public const TYPES = [
        'industries' => [Industry::class, 'Lĩnh vực', 'linh-vuc'],
        'occupations' => [JobCategory::class, 'Nhóm nghề CNTT', 'nhom-nghe'],
        'specialties' => [JobSpecialization::class, 'Chuyên môn CNTT', 'chuyen-mon'],
        'levels' => [JobLevel::class, 'Cấp bậc', 'cap-bac'],
        'skills' => [Skill::class, 'Kỹ năng', 'ky-nang'],
    ];

    public static function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    public static function cleanName(string $name): string
    {
        $name = Normalizer::normalize($name, Normalizer::FORM_KC);

        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $name));
    }

    public static function normalizedName(string $name): string
    {
        // Giữ dấu, dấu +/# và các ký hiệu phân biệt kỹ năng; không dùng slug làm khóa tên.
        return mb_strtolower(self::cleanName($name), 'UTF-8');
    }
}
