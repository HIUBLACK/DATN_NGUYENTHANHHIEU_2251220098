<?php

namespace App\Support;

class OccupationCatalog
{
    public function occupations(): array
    {
        $groups = [
            ['Phát triển phần mềm', 'Xây dựng và phát triển ứng dụng, hệ thống phần mềm.'],
            ['Kiểm thử phần mềm', 'Đảm bảo chất lượng và kiểm thử sản phẩm phần mềm.'],
            ['An toàn thông tin', 'Đánh giá và bảo vệ an toàn hệ thống thông tin.'],
            ['Dữ liệu và AI', 'Phân tích dữ liệu và phát triển mô hình học máy.'],
            ['Hạ tầng và mạng', 'Vận hành hệ thống, mạng và nền tảng đám mây.'],
            ['Thiết kế sản phẩm số', 'Thiết kế trải nghiệm và giao diện sản phẩm.'],
            ['Hỗ trợ CNTT', 'Hỗ trợ kỹ thuật và người dùng.'],
        ];
        $items = [];
        foreach ($groups as $index => [$name, $description]) {
            $items[] = [
                'id' => 'NN-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'description' => $description,
                'status' => $index === 6 ? 'inactive' : 'active',
                'created' => now('Asia/Ho_Chi_Minh')->subDays(20)->format('Y-m-d'),
            ];
        }

        return $items;
    }

    public function specialties(): array
    {
        $samples = [
            ['Frontend Developer', 'NN-001'],
            ['Backend Developer', 'NN-001'],
            ['Fullstack Developer', 'NN-001'],
            ['Manual Tester', 'NN-002'],
            ['Automation Tester', 'NN-002'],
            ['Penetration Tester', 'NN-003'],
            ['Data Analyst', 'NN-004'],
            ['Machine Learning Engineer', 'NN-004'],
            ['IT Support', 'NN-007'],
            ['DevOps Engineer', 'NN-005'],
            ['UI/UX Designer', 'NN-006'],
            ['Mobile Developer', 'NN-001'],
        ];
        $items = [];
        foreach ($samples as $index => [$name, $occupationId]) {
            $items[] = [
                'id' => 'CM-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'occupationId' => $occupationId,
                'description' => 'Chuyên môn minh họa: '.$name.'.',
                'status' => $index === 8 ? 'inactive' : 'active',
                'created' => now('Asia/Ho_Chi_Minh')->subDays(20)->format('Y-m-d'),
            ];
        }

        return $items;
    }
}
