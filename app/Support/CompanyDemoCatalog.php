<?php

namespace App\Support;

class CompanyDemoCatalog
{
    public function __construct(private CandidateCatalog $jobs) {}

    public function profiles(): array
    {
        $companies = [];
        foreach ($this->jobs->jobs()->groupBy('company')->values() as $index => $jobs) {
            $first = $jobs->first();
            $companies[] = [
                'id' => $index + 1,
                'name' => $first['company'],
                'short_name' => $first['company'],
                'summary' => 'Doanh nghiệp công nghệ minh họa, cùng phát triển sản phẩm số.',
                'description' => 'Đây là hồ sơ doanh nghiệp giả định để trải nghiệm giao diện. Đội ngũ làm việc theo nhóm, chia sẻ kiến thức và cải tiến chất lượng sản phẩm.',
                'founded_year' => 2018 + $index,
                'size' => '51–200',
                'industry' => 'Công nghệ thông tin',
                'company_type' => 'Phát triển sản phẩm và dịch vụ phần mềm',
                'country' => 'Việt Nam',
                'website' => 'https://example.com',
                'address' => $first['location'].' — địa điểm minh họa',
                'working_hours' => 'Thứ Hai – Thứ Sáu, 8:30–17:30',
                'ot_policy' => 'Làm thêm theo thỏa thuận trước và có chế độ bù phù hợp.',
                'benefits' => "Đào tạo chuyên môn\nHoạt động gắn kết đội ngũ\nHỗ trợ thiết bị làm việc",
                'technologies' => $jobs->pluck('skills')->flatten()->unique()->implode(', '),
                'logo_url' => asset('jobboard/images/'.$first['logo']),
                'cover_url' => null,
                'gallery_urls' => [],
                'status' => 'active',
            ];
        }

        return $companies;
    }

    public function find(int $id): ?array
    {
        return collect($this->profiles())->firstWhere('id', $id);
    }
}
