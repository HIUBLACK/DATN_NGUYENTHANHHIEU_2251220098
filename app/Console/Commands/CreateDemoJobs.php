<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\JobCatalog;
use App\Services\RecruiterJobService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateDemoJobs extends Command
{
    protected $signature = 'jobs:demo {company : ID doanh nghiệp nhận tin mẫu}';

    protected $description = 'Tạo ba tin Java demo ở trạng thái nháp, không tự công khai hoặc thêm danh mục';

    public function handle(RecruiterJobService $jobs): int
    {
        $company = Company::with('recruiter')->findOrFail($this->argument('company'));
        $position = JobCatalog::find('positions', 1);
        $industry = JobCatalog::find('industries', 1);
        $skills = JobCatalog::all('skills')->whereIn('name', ['Git', 'GitHub'])->pluck('id')->all();
        $samples = [
            ['Fresher', '01', 'Hà Nội', 8000000, 12000000, 'onsite'],
            ['Junior', '48', 'Đà Nẵng', 15000000, 22000000, 'hybrid'],
            ['Senior', '79', 'Hồ Chí Minh', 30000000, 45000000, 'remote'],
        ];

        $created = DB::transaction(function () use ($company, $jobs, $position, $industry, $skills, $samples) {
            // Serialize repeat runs for this owner, in the same lock order as job creation.
            $owner = $company->recruiter()->lockForUpdate()->firstOrFail();
            $result = [];
            foreach ($samples as [$levelName, $province, $city, $minimum, $maximum, $mode]) {
                $title = '[DEMO] Lập trình viên Java '.$levelName;
                if ($company->jobs()->whereHas('versions', fn ($query) => $query->where('title', $title))->exists()) {
                    continue;
                }
                $level = JobCatalog::all('levels')->firstWhere('name', $levelName);
                $job = $jobs->create($owner, [
                    'title' => $title,
                    'job_position_id' => $position?->id,
                    'industry_id' => $industry?->id,
                    'job_level_id' => $level?->id,
                    'province_code' => $province,
                    'location' => 'Văn phòng minh họa tại '.$city.' — địa chỉ demo, cần cập nhật trước khi đăng.',
                    'description' => 'Đây là tin tuyển dụng giả phục vụ kiểm thử giao diện, không phải nhu cầu tuyển dụng thật. Tham gia phát triển API Java/Spring Boot, xây dựng chức năng quản lý dữ liệu và phối hợp với nhóm kiểm thử.',
                    'requirements' => 'Kiến thức Java, lập trình hướng đối tượng, SQL và Git. Có khả năng đọc tài liệu kỹ thuật, làm việc nhóm và viết mã dễ bảo trì. Phạm vi trách nhiệm được điều chỉnh theo cấp bậc '.$levelName.'.',
                    'benefits' => 'Thông tin minh họa: đào tạo nội bộ, hỗ trợ thiết bị làm việc, nghỉ phép và đánh giá năng lực định kỳ. Cần thay bằng chính sách thực tế trước khi gửi duyệt.',
                    'salary_min' => $minimum,
                    'salary_max' => $maximum,
                    'work_mode' => $mode,
                    'employment_type' => 'full_time',
                    'quantity' => 2,
                    'application_deadline' => now('Asia/Ho_Chi_Minh')->addDays(30)->toDateString(),
                    'skills' => $skills,
                ]);
                $result[] = [$job->id, $title, 'Bản nháp'];
            }

            return $result;
        });

        $this->table(['ID', 'Tiêu đề', 'Trạng thái'], $created);
        $this->info('Đã tạo '.count($created).' tin mẫu cho '.$company->name.'. Tin đã có được bỏ qua.');

        return self::SUCCESS;
    }
}
