<?php

namespace App\Support;

class RecruiterCatalog
{
    public function data(): array
    {
        $company = [
            'name' => 'VietTech Demo',
            'description' => 'Doanh nghiệp công nghệ minh họa, phát triển sản phẩm web và ứng dụng. Đây không phải hồ sơ doanh nghiệp thật.',
            'website' => 'https://example.com',
            'address' => 'Hà Nội — địa chỉ minh họa',
            'email' => 'recruiter@example.com',
            'phone' => '0900000000',
            'logo' => '',
        ];
        $jobs = [];
        $states = [
            ['Backend Laravel Developer', 'open', 'approved'],
            ['Frontend React Developer', 'paused', 'approved'],
            ['Kỹ sư DevOps', 'draft', 'pending'],
            ['Lập trình viên Mobile', 'draft', 'rejected'],
            ['Thực tập sinh kiểm thử', 'draft', 'draft'],
            ['Kỹ sư dữ liệu', 'closed', 'approved'],
            ['Kỹ sư AI', 'blocked', 'approved'],
            ['Lập trình viên Java', 'paused', 'approved'],
        ];

        foreach ($states as $index => [$title, $activity, $moderation]) {
            $specialtyIds = ['CM-002', 'CM-001', 'CM-010', 'CM-012', 'CM-004', 'CM-007', 'CM-008', 'CM-002'];
            $specialty = collect(app(OccupationCatalog::class)->specialties())->firstWhere('id', $specialtyIds[$index]);
            $content = [
                'occupationId' => $specialty['occupationId'],
                'specialtyId' => $specialty['id'],
                'title' => $title,
                'position' => 'Kỹ sư phần mềm',
                'description' => "Phát triển sản phẩm công nghệ.\nPhối hợp kiểm thử và cải tiến chất lượng phần mềm.",
                'requirements' => 'Có kiến thức chuyên môn và khả năng làm việc nhóm.',
                'benefits' => 'Hỗ trợ đào tạo, thu nhập theo năng lực và môi trường chia sẻ kiến thức.',
                'salary_min' => 15,
                'salary_max' => 25,
                'location' => 'Hà Nội',
                'mode' => 'Onsite',
                'type' => 'Toàn thời gian',
                'quantity' => 2,
                'experience' => '1–3 năm',
                'skills' => 'PHP, Laravel, MySQL',
                'deadline' => $index === 7 ? now()->subDay()->format('Y-m-d') : now()->addDays(30)->format('Y-m-d'),
            ];
            $jobs[] = [
                'id' => (string) ($index + 1),
                'created' => now()->subDays(5)->format('Y-m-d'),
                'activity' => $activity,
                'moderation' => $moderation,
                'submitted' => $moderation === 'draft' ? null : now()->subDays(2)->format('d/m/Y H:i'),
                'reason' => $moderation === 'rejected' ? 'Mô tả vị trí chưa đủ rõ; cần bổ sung yêu cầu chuyên môn.' : '',
                'content' => $content,
                'published' => $moderation === 'approved' ? $content : null,
            ];
        }

        $applications = [];
        $names = ['Nguyễn Minh An', 'Trần Thu Hà', 'Lê Quốc Bảo', 'Phạm Mai Anh', 'Đỗ Hoàng Nam', 'Vũ Thanh Linh'];
        foreach (['applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn'] as $index => $status) {
            $applications[] = [
                'id' => (string) ($index + 1),
                'jobId' => (string) ($index % 2 + 1),
                'name' => $names[$index],
                'email' => 'candidate'.($index + 1).'@example.com',
                'phone' => 'Chưa cung cấp trong dữ liệu mẫu',
                'date' => now()->subDays($index)->format('d/m/Y H:i'),
                'status' => $status,
                'cv' => $index === 0 ? asset('recruiter/demo/cv-demo-01.pdf') : null,
                'notes' => [],
                'history' => [[
                    'status' => $status,
                    'date' => now()->subDays($index)->format('d/m/Y H:i'),
                    'actor' => 'Dữ liệu minh họa ban đầu',
                ]],
            ];
        }

        return [
            'occupations' => app(OccupationCatalog::class)->occupations(),
            'specialties' => app(OccupationCatalog::class)->specialties(),
            'company' => $company,
            'jobs' => $jobs,
            'applications' => $applications,
            'notifications' => [
                ['id' => '1', 'type' => 'application', 'target' => '1', 'title' => 'Nguyễn Minh An đã ứng tuyển Backend Laravel Developer', 'date' => now()->format('d/m/Y H:i'), 'read' => false],
                ['id' => '2', 'type' => 'job', 'target' => '1', 'title' => 'Tin Backend Laravel Developer đã được duyệt (minh họa)', 'date' => now()->subDay()->format('d/m/Y H:i'), 'read' => false],
                ['id' => '3', 'type' => 'job', 'target' => '4', 'title' => 'Tin Lập trình viên Mobile bị từ chối: mô tả chưa đủ rõ, cần bổ sung yêu cầu chuyên môn.', 'date' => now()->subDay()->format('d/m/Y H:i'), 'read' => true],
            ],
        ];
    }
}
