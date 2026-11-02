<?php

namespace App\Support;

/** Presentation fixtures only. Replace this provider with application services later. */
final class DemoCatalog
{
    public static function companies(): array
    {
        return [
            ['id' => 1, 'name' => 'Nexa Technology', 'initials' => 'NX', 'sector' => 'Phần mềm & Sản phẩm số', 'location' => 'TP. Hồ Chí Minh', 'size' => '100–250 nhân sự', 'color' => 'purple'],
            ['id' => 2, 'name' => 'GreenByte', 'initials' => 'GB', 'sector' => 'Công nghệ tài chính', 'location' => 'Hà Nội', 'size' => '50–100 nhân sự', 'color' => 'green'],
            ['id' => 3, 'name' => 'CloudNine', 'initials' => 'C9', 'sector' => 'Hạ tầng & Điện toán đám mây', 'location' => 'Đà Nẵng', 'size' => '100–250 nhân sự', 'color' => 'blue'],
            ['id' => 4, 'name' => 'Pixel Studio', 'initials' => 'PX', 'sector' => 'Thiết kế & Trải nghiệm số', 'location' => 'TP. Hồ Chí Minh', 'size' => '25–50 nhân sự', 'color' => 'orange'],
        ];
    }

    public static function jobs(): array
    {
        $rows = [
            ['Lập trình viên Backend Laravel', 1, 'Backend', ['PHP', 'Laravel', 'MySQL', 'REST API', 'Redis', 'Docker'], 25, 40, 'Có kinh nghiệm', 'Kết hợp', 'Toàn thời gian', 'open'],
            ['Lập trình viên Frontend React', 2, 'Frontend', ['React', 'TypeScript', 'CSS'], 22, 35, 'Có kinh nghiệm', 'Tại văn phòng', 'Toàn thời gian', 'open'],
            ['Kỹ sư DevOps', 3, 'DevOps', ['Docker', 'Kubernetes', 'Linux'], 35, 55, 'Chuyên gia', 'Từ xa', 'Toàn thời gian', 'open'],
            ['Chuyên viên thiết kế UI/UX', 4, 'UI/UX', ['Figma', 'UI Design', 'UX Research'], 18, 30, 'Có kinh nghiệm', 'Kết hợp', 'Toàn thời gian', 'open'],
            ['Lập trình viên Fullstack', 1, 'Fullstack', ['PHP', 'Laravel', 'React', 'MySQL'], 28, 45, 'Có kinh nghiệm', 'Từ xa', 'Toàn thời gian', 'open'],
            ['Thực tập sinh kiểm thử phần mềm', 2, 'QA/Tester', ['Testing', 'SQL', 'Postman'], 4, 8, 'Thực tập sinh', 'Tại văn phòng', 'Thực tập', 'open'],
            ['Lập trình viên ứng dụng di động', 3, 'Mobile', ['Flutter', 'Dart', 'REST API'], 20, 35, 'Mới tốt nghiệp', 'Kết hợp', 'Toàn thời gian', 'open'],
            ['Chuyên viên phân tích dữ liệu', 2, 'Data', ['Python', 'SQL', 'Power BI'], 20, 32, 'Có kinh nghiệm', 'Từ xa', 'Bán thời gian', 'open'],
            ['Kỹ sư trí tuệ nhân tạo', 1, 'AI/ML', ['Python', 'PyTorch', 'Machine Learning'], 35, 60, 'Chuyên gia', 'Kết hợp', 'Toàn thời gian', 'closed'],
            ['Kỹ sư kiểm thử tự động', 3, 'QA/Tester', ['Selenium', 'Java', 'Testing'], 22, 38, 'Có kinh nghiệm', 'Tại văn phòng', 'Toàn thời gian', 'expired'],
            ['Tin không khả dụng', 4, 'Backend', [], 0, 0, 'Có kinh nghiệm', 'Từ xa', 'Toàn thời gian', 'hidden'],
        ];

        return array_map(function ($r, $i) {
            $company = self::companies()[$r[1] - 1];

            return ['id' => $i + 1, 'title' => $r[0], 'company' => $company, 'category' => $r[2], 'skills' => $r[3], 'min' => $r[4], 'max' => $r[5], 'level' => $r[6], 'mode' => $r[7], 'type' => $r[8], 'status' => $r[9], 'location' => $company['location'], 'deadline' => $r[9] === 'expired' ? now()->subDays(5)->format('d/m/Y') : now()->addDays(30)->format('d/m/Y')];
        }, $rows, array_keys($rows));
    }

    public static function publicJobs(): array
    {
        return array_values(array_filter(self::jobs(), fn ($j) => $j['status'] !== 'hidden'));
    }

    public static function categories(): array
    {
        return ['Backend', 'Frontend', 'Fullstack', 'Mobile', 'DevOps', 'Data', 'AI/ML', 'QA/Tester', 'UI/UX'];
    }
}
