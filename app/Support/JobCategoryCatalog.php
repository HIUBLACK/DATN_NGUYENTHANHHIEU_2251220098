<?php

namespace App\Support;

class JobCategoryCatalog
{
    public function data(): array
    {
        $groups = [
            'industries' => ['LV', ['Phát triển phần mềm', 'Hạ tầng và điện toán đám mây', 'Thiết kế sản phẩm số', 'Dữ liệu và AI', 'An toàn thông tin']],
            'skills' => ['KN', ['PHP', 'Laravel', 'MySQL', 'REST API', 'Redis', 'Docker', 'React', 'TypeScript', 'CSS', 'Linux', 'Kubernetes', 'Figma', 'UI Design', 'UX Research', 'Testing', 'SQL', 'Postman', 'Flutter', 'Dart', 'Python', 'Power BI', 'PyTorch', 'Java', 'Selenium', 'JavaScript', 'C#', '.NET', 'Node.js', 'Vue.js', 'Angular', 'Git', 'AWS', 'Azure', 'Go', 'C++', 'MongoDB']],
            'levels' => ['CB', ['Thực tập sinh', 'Có kinh nghiệm', 'Mới tốt nghiệp', 'Trưởng nhóm', 'Quản lý', 'Giám đốc']],
        ];
        $result = [];
        foreach ($groups as $key => [$prefix, $names]) {
            $result[$key] = [];
            foreach ($names as $index => $name) {
                $result[$key][] = [
                    'id' => $prefix.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'filterValue' => $name,
                    'description' => 'Danh mục minh họa '.$name,
                    'status' => 'active',
                    'created' => '2026-11-14',
                ];
            }
        }

        return $result;
    }
}
