<?php

namespace App\Support;

class AdminCatalog
{
    public function data(): array
    {
        $users = [];
        $roles = ['admin', 'admin', 'recruiter', 'recruiter', 'candidate', 'candidate', 'candidate', 'candidate', 'candidate', 'candidate'];
        foreach ($roles as $index => $role) {
            $users[] = [
                'id' => (string) ($index + 1),
                'name' => ucfirst($role).' mẫu '.($index + 1),
                'email' => $role.($index + 1).'@example.com',
                'role' => $role,
                'status' => $index === 9 ? 'blocked' : 'active',
                'created' => $this->date(25 - $index),
                'profile' => $role === 'candidate' ? 'Ứng viên CNTT giả định. Kỹ năng: PHP, JavaScript.' : 'Tài khoản minh họa, không phải người dùng thật.',
                'history' => $index === 9 ? [$this->history('Khóa tài khoản', 'blocked', 'Dữ liệu minh họa: cần xác minh tài khoản.')] : [],
            ];
        }
        $companies = [];
        foreach (['Công nghệ Mẫu A', 'Phần mềm Mẫu B', 'Dữ liệu Mẫu C'] as $index => $name) {
            $companies[] = [
                'id' => (string) ($index + 1),
                'name' => $name,
                'managerId' => $index === 1 ? '4' : '3',
                'short_name' => 'Công ty mẫu '.($index + 1),
                'summary' => 'Doanh nghiệp công nghệ minh họa, phát triển sản phẩm và dịch vụ số.',
                'founded_year' => 2020 - $index,
                'size' => '51–200 nhân sự',
                'industry' => 'Công nghệ thông tin',
                'company_type' => 'Phát triển phần mềm và dịch vụ',
                'country' => 'Việt Nam',
                'working_hours' => 'Thứ Hai – Thứ Sáu, 8:30–17:30',
                'overtime_policy' => 'Làm thêm theo thỏa thuận và chính sách của doanh nghiệp.',
                'benefits' => "Đào tạo chuyên môn\nHoạt động gắn kết đội ngũ\nHỗ trợ thiết bị làm việc",
                'technologies' => 'PHP, Laravel, JavaScript, MySQL',
                'logo_url' => asset('jobboard/images/job_logo_'.($index + 1).'.jpg'),
                'cover_url' => $index === 0 ? asset('jobboard/images/hero_1.jpg') : null,
                'gallery_urls' => $index === 0 ? [asset('jobboard/images/hero_1.jpg')] : [],

                'email' => 'company'.($index + 1).'@example.com',
                'website' => 'https://example.com',
                'address' => 'Địa chỉ minh họa tại Hà Nội',
                'phone' => 'Không có số điện thoại thật',
                'description' => 'Doanh nghiệp giả định dùng để kiểm tra giao diện quản trị VIEJOB.',
                'status' => $index === 2 ? 'blocked' : 'active',
                'history' => $index === 2 ? [$this->history('Khóa doanh nghiệp', 'blocked', 'Chờ xác minh thông tin minh họa.')] : [],
            ];
        }
        $jobs = $this->jobs();
        $applications = [];
        foreach (['applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn', 'applied', 'reviewing', 'applied'] as $index => $status) {
            $history = [['action' => 'Nộp hồ sơ', 'result' => 'applied', 'actor' => 'Ứng viên mẫu', 'time' => $this->date(8 - $index).' 09:00', 'reason' => '', 'version' => '']];
            if ($status !== 'applied') {
                $history[] = ['action' => 'Cập nhật trạng thái mẫu', 'result' => $status, 'actor' => $status === 'withdrawn' ? 'Ứng viên mẫu' : 'Recruiter mẫu', 'time' => $this->date(8 - $index).' 14:00', 'reason' => '', 'version' => ''];
            }
            $applications[] = [
                'id' => (string) ($index + 1),
                'userId' => (string) (5 + $index % 6),
                'jobId' => (string) (1 + $index % 2),
                'created' => $this->date(8 - $index),
                'status' => $status,
                'history' => $history,
            ];
        }


        return compact('users', 'companies', 'jobs', 'applications') + ['currentAdminId' => '1', 'today' => $this->date(0)];
    }

    private function jobs(): array
    {
        $jobs = [];
        $samples = [
            ['Backend Laravel Developer', 'open', 'pending', true],
            ['Frontend JavaScript Developer', 'open', 'approved', true],
            ['Kỹ sư dữ liệu', 'draft', 'pending', false],
            ['Kỹ sư DevOps', 'draft', 'rejected', false],
            ['Thực tập sinh kiểm thử', 'draft', 'draft', false],
            ['Lập trình viên Java', 'paused', 'approved', true],
            ['Kỹ sư hệ thống', 'closed', 'approved', true],
            ['Lập trình viên PHP', 'blocked', 'approved', true],
        ];
        foreach ($samples as $index => [$title, $activity, $moderation, $published]) {
            $content = [
                'title' => $title,
                'description' => "Xây dựng và bảo trì phần mềm.\nPhối hợp với nhóm phát triển sản phẩm.",
                'requirements' => 'Có kiến thức chuyên môn CNTT, khả năng làm việc nhóm.',
                'benefits' => 'Đào tạo chuyên môn và môi trường chia sẻ kiến thức.',
                'skills' => 'Laravel, JavaScript, MySQL',
                'salary' => '15–25 triệu đồng/tháng',
                'location' => 'Hà Nội',
                'mode' => 'Làm việc tại văn phòng',
                'type' => 'Toàn thời gian',
                'quantity' => '2',
                'experience' => '1–3 năm',
                'deadline' => now('Asia/Ho_Chi_Minh')->addDays(20)->format('Y-m-d'),
            ];
            $publishedContent = $published ? $content : null;
            if ($index === 0) {
                $content['salary'] = '20–30 triệu đồng/tháng';
                $content['mode'] = 'Kết hợp trực tiếp và từ xa';
                $content['requirements'] = 'Có ít nhất 2 năm kinh nghiệm Laravel và kiểm thử tự động.';
            }
            $jobs[] = [
                'id' => (string) ($index + 1),
                'companyId' => (string) ($index % 2 + 1),
                'created' => $this->date(14 - $index * 2),
                'activity' => $activity,
                'previousActivity' => $activity === 'blocked' ? 'closed' : null,
                'moderation' => $moderation,
                'version' => $index === 0 ? 2 : 1,
                'publishedVersion' => $published ? 1 : null,
                'content' => $content,
                'published' => $publishedContent,
                'reason' => $moderation === 'rejected' ? 'Yêu cầu công việc chưa đủ rõ.' : '',
                'history' => [$this->history('Khởi tạo dữ liệu minh họa', $moderation, $moderation === 'rejected' ? 'Yêu cầu công việc chưa đủ rõ.' : '', $index === 0 ? 2 : 1)],
            ];
        }

        return $jobs;
    }

    private function date(int $daysAgo): string
    {
        return now('Asia/Ho_Chi_Minh')->subDays($daysAgo)->format('Y-m-d');
    }

    private function history(string $action, string $result, string $reason = '', int $version = 0): array
    {
        return ['action' => $action, 'result' => $result, 'reason' => $reason, 'version' => $version ?: '', 'actor' => 'Admin mẫu 1', 'time' => $this->date(1).' 09:00'];
    }
}
