<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CandidateCatalog
{
    public function jobs(): Collection
    {
        $positions = [
            ['Backend Laravel Developer', 'PHP, Laravel, MySQL, REST API, Redis, Docker', 'Hà Nội'],
            ['Frontend React Developer', 'React, TypeScript, CSS', 'TP. Hồ Chí Minh'],
            ['Kỹ sư DevOps', 'Docker, Linux, Kubernetes', 'Đà Nẵng'],
            ['Thiết kế UI/UX', 'Figma, UI Design, UX Research', 'Hà Nội'],
            ['Lập trình viên Fullstack', 'Laravel, React, MySQL', 'TP. Hồ Chí Minh'],
            ['Thực tập sinh kiểm thử', 'Testing, SQL, Postman', 'Đà Nẵng'],
            ['Lập trình viên Mobile', 'Flutter, Dart, REST API', 'Hà Nội'],
            ['Chuyên viên dữ liệu', 'Python, SQL, Power BI', 'TP. Hồ Chí Minh'],
            ['Kỹ sư AI/ML', 'Python, PyTorch', 'Đà Nẵng'],
            ['Kỹ sư kiểm thử tự động', 'Java, Selenium', 'Hà Nội'],
        ];

        $companies = ['Nexa Technology', 'GreenByte', 'CloudNine', 'Pixel Studio', 'Viet Software'];
        $jobs = [];

        foreach ($positions as $index => $position) {
            $status = 'open';

            if ($index === 8) {
                $status = 'closed';
            }

            if ($index === 9) {
                $status = 'expired';
            }

            $jobs[] = [
                'id' => $index + 1,
                'title' => $position[0],
                'skills' => explode(', ', $position[1]),
                'location' => $position[2],
                'company' => $companies[$index % 5],
                'logo' => 'job_logo_'.(($index % 5) + 1).'.jpg',
                'salary_min' => 15 + $index * 2,
                'salary_max' => 25 + $index * 3,
                'level' => $index === 5 ? 'Thực tập sinh' : 'Có kinh nghiệm',
                'mode' => ['Tại văn phòng', 'Từ xa', 'Kết hợp'][$index % 3],
                'type' => $index === 5 ? 'Thực tập' : 'Toàn thời gian',
                'deadline' => $status === 'expired' ? now()->subDays(3) : now()->addDays(30),
                'status' => $status,
            ];
        }

        return collect($jobs);
    }

    public function find(int $id): ?array
    {
        return $this->jobs()->firstWhere('id', $id);
    }

    public function filters(): array
    {
        return [
            'location' => ['Địa điểm', ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng']],
            'level' => ['Cấp bậc', ['Thực tập sinh', 'Có kinh nghiệm']],
            'mode' => ['Hình thức làm việc', ['Tại văn phòng', 'Từ xa', 'Kết hợp']],
            'type' => ['Loại công việc', ['Toàn thời gian', 'Bán thời gian', 'Thực tập']],
        ];
    }

    public function search(array $filters): Collection
    {
        return $this->jobs()->filter(function (array $job) use ($filters) {
            $keyword = mb_strtolower(trim($filters['q'] ?? ''));
            $searchText = mb_strtolower($job['title'].' '.$job['company'].' '.implode(' ', $job['skills']));

            if ($keyword !== '' && ! str_contains($searchText, $keyword)) {
                return false;
            }

            foreach (['location', 'level', 'mode', 'type'] as $field) {
                if (! empty($filters[$field]) && $job[$field] !== $filters[$field]) {
                    return false;
                }
            }

            return $job['salary_max'] >= (int) ($filters['salary'] ?? 0);
        })->values();
    }

    public function statuses(): array
    {
        return [
            'open' => 'Đang tuyển',
            'closed' => 'Đã đóng',
            'expired' => 'Hết hạn',
            'pending' => 'Chờ phân tích',
            'processing' => 'Đang phân tích',
            'needs_review' => 'Cần xác nhận',
            'completed' => 'Đã xác nhận',
            'failed' => 'Phân tích thất bại',
            'applied' => 'Đã ứng tuyển',
            'reviewing' => 'Đang xem xét',
            'interview' => 'Mời phỏng vấn',
            'hired' => 'Trúng tuyển',
            'rejected' => 'Từ chối',
            'withdrawn' => 'Đã rút',
        ];
    }

    public function recommendations(?array $resume, array $filters): array
    {
        if (! $resume || $resume['status'] !== 'completed') {
            return [];
        }

        $skills = array_map('mb_strtolower', $resume['skills']);
        $recommendations = [];

        foreach ($this->search($filters)->where('status', 'open') as $job) {
            $matched = [];
            $missing = [];

            foreach ($job['skills'] as $skill) {
                if (in_array(mb_strtolower($skill), $skills, true)) {
                    $matched[] = $skill;
                } else {
                    $missing[] = $skill;
                }
            }

            $recommendations[] = [
                'job' => $job,
                'score' => max(55, 89 - $job['id'] * 3),
                'matched' => $matched,
                'missing' => $missing,
            ];
        }

        return $recommendations;
    }
}
