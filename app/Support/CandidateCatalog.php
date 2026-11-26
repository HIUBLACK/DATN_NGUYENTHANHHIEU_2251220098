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
            ['Backend Developer tại Đà Nẵng', 'PHP, Laravel, MySQL', 'Đà Nẵng'],
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

            $specialtyIds = ['CM-002', 'CM-001', 'CM-010', 'CM-011', 'CM-003', 'CM-004', 'CM-012', 'CM-007', 'CM-008', 'CM-005', 'CM-002'];
            $specialty = collect(app(OccupationCatalog::class)->specialties())->firstWhere('id', $specialtyIds[$index]);
            $jobs[] = [
                'industry' => ['Phát triển phần mềm', 'Phát triển phần mềm', 'Hạ tầng và điện toán đám mây', 'Thiết kế sản phẩm số', 'Phát triển phần mềm', 'Phát triển phần mềm', 'Phát triển phần mềm', 'Dữ liệu và AI', 'Dữ liệu và AI', 'Phát triển phần mềm', 'Phát triển phần mềm'][$index],
                'occupationId' => $specialty['occupationId'],
                'specialtyId' => $specialty['id'],
                'experience' => $index === 5 ? 'Chưa có kinh nghiệm' : ($index % 2 === 0 ? '1–3 năm' : 'Trên 3 năm'),
                'posted_at' => now('Asia/Ho_Chi_Minh')->subDays($index)->format('Y-m-d'),
                'moderation' => 'approved',
                'blocked' => false,
                'company_status' => 'active',
                'recruiter_status' => 'active',
                'id' => $index + 1,
                'title' => $position[0],
                'skills' => explode(', ', $position[1]),
                'location' => $position[2],
                'company' => $companies[$index % 5],
                'company_demo_id' => $index % 5 + 1,
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
            'industry' => ['Lĩnh vực', JobCatalog::all('industries')->pluck('name')->all()],
            'company' => ['Công ty', $this->publicJobs()->pluck('company')->unique()->values()->all()],
            'location' => ['Địa điểm', ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng']],
            'level' => ['Cấp bậc', JobCatalog::all('levels')->pluck('name')->all()],
            'mode' => ['Hình thức làm việc', ['Tại văn phòng', 'Từ xa', 'Kết hợp']],
            'experience' => ['Kinh nghiệm', ['Chưa có kinh nghiệm', '1–3 năm', 'Trên 3 năm']],
            'skill' => ['Kỹ năng yêu cầu', JobCatalog::all('skills')->pluck('name')->all()],
            'type' => ['Loại công việc', ['Toàn thời gian', 'Bán thời gian', 'Thực tập']],
        ];
    }

    public function search(array $filters): Collection
    {
        $results = $this->publicJobs()->filter(function (array $job) use ($filters) {
            $keyword = mb_strtolower(trim($filters['q'] ?? ''));
            $searchText = mb_strtolower($job['title'].' '.$job['company'].' '.implode(' ', $job['skills']));

            if ($keyword !== '' && ! str_contains($searchText, $keyword)) {
                return false;
            }

            foreach (['industry', 'company', 'location', 'level', 'mode', 'type', 'experience', 'occupationId', 'specialtyId'] as $field) {
                if (! empty($filters[$field]) && $job[$field] !== $filters[$field]) {
                    return false;
                }
            }

            if (! empty($filters['skill']) && ! in_array($filters['skill'], $job['skills'], true)) {
                return false;
            }

            return $job['salary_max'] >= (int) ($filters['salary'] ?? 0);
        });

        return (($filters['sort'] ?? 'newest') === 'salary'
            ? $results->sortByDesc('salary_max')
            : $results->sortByDesc('posted_at'))->values();
    }

    public function isPublic(array $job): bool
    {
        return $job['moderation'] === 'approved'
            && $job['status'] === 'open'
            && ! $job['blocked']
            && $job['deadline']->toDateString() >= now('Asia/Ho_Chi_Minh')->toDateString()
            && $job['company_status'] === 'active'
            && $job['recruiter_status'] === 'active';
    }

    public function publicJobs(): Collection
    {
        return $this->jobs()->filter(fn (array $job) => $this->isPublic($job))->values();
    }

    public function statuses(): array
    {
        return [
            'open' => 'Đang tuyển',
            'closed' => 'Đã đóng',
            'expired' => 'Hết hạn',
            'pending' => 'Chờ phân tích',
            'processing' => 'Đang phân tích',
            'needs_review' => 'Đã phân tích',
            'completed' => 'Đã phân tích',
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
        if (! $resume || ! in_array($resume['status'], ['completed', 'needs_review'], true)) {
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
