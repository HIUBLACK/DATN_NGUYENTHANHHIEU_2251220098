<?php

namespace App\Support;

class CompanyOptions
{
    public const TYPES = [
        'Công ty sản phẩm (Product)', 'Công ty gia công phần mềm (Outsourcing)',
        'Công ty dịch vụ CNTT và tư vấn', 'Công ty cung cấp nhân sự CNTT (Staffing)',
        'Công ty tích hợp hệ thống', 'Công ty phần cứng và thiết bị',
        'Công ty viễn thông và hạ tầng', 'Công ty thương mại điện tử',
        'Công ty khởi nghiệp (Startup)', 'Trung tâm công nghệ nội bộ (In-house)',
        'Trung tâm nghiên cứu và phát triển (R&D)', 'Tập đoàn đa ngành',
        'Cơ sở giáo dục và đào tạo', 'Cơ quan nhà nước',
        'Tổ chức phi lợi nhuận', 'Hộ kinh doanh / Doanh nghiệp tư nhân', 'Mô hình khác',
    ];

    public const DAYS = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật'];

    public const OVERTIME = ['Có làm thêm giờ', 'Không làm thêm giờ'];

    public static function countries(): array
    {
        return json_decode(file_get_contents(resource_path('data/countries-vi.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
