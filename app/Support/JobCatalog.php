<?php

namespace App\Support;

use Illuminate\Support\Collection;
use stdClass;

/** Danh sách lựa chọn cố định; không truy vấn hoặc ghi bảng danh mục. */
class JobCatalog
{
    public static function all(string $group): Collection
    {
        return collect(config('job-catalog.'.$group, []))
            ->map(function ($value, $id) use ($group) {
                $item = (object) (is_array($value) ? $value : ['name' => $value]);
                $item->id = (int) $id;
                if ($group === 'specializations') {
                    $item->category = self::find('categories', $item->job_category_id);
                }

                return $item;
            })->sortBy('name')->values();
    }

    public static function find(string $group, int|string|null $id): ?stdClass
    {
        return self::all($group)->firstWhere('id', (int) $id);
    }

    public static function matchingSkills(array $names): Collection
    {
        return self::all('skills')->filter(function ($skill) use ($names) {
            return in_array(mb_strtolower(trim($skill->name)), $names, true);
        });
    }
}
