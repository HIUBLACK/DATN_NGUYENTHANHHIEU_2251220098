<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Support\CandidateCatalog;
use Carbon\CarbonImmutable;

class AdminDashboardService
{
    public function data(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start'] ?? now('Asia/Ho_Chi_Minh')->subDays(29)->toDateString(), 'Asia/Ho_Chi_Minh')->startOfDay();
        $end = CarbonImmutable::parse($filters['end'] ?? now('Asia/Ho_Chi_Minh')->toDateString(), 'Asia/Ho_Chi_Minh')->startOfDay();
        $users = User::whereIn('role', ['candidate', 'recruiter'])->select('role', 'status')->selectRaw('COUNT(*) AS total')->groupBy('role', 'status')->toBase()->get();
        $companies = Company::select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $jobs = Job::select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $applications = Application::select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $userMetrics = [
            ['Ứng viên', (int) $users->where('role', 'candidate')->sum('total'), 'people'],
            ['Nhà tuyển dụng', (int) $users->where('role', 'recruiter')->sum('total'), 'person-workspace'],
            ['Tài khoản hoạt động', (int) $users->where('status', 'active')->sum('total'), 'person-check'],
            ['Tài khoản bị khóa', (int) $users->where('status', 'locked')->sum('total'), 'person-lock'],
        ];
        $systemMetrics = [
            ['Tổng doanh nghiệp', (int) $companies->sum(), 'buildings'],
            ['Doanh nghiệp bị khóa', (int) ($companies['locked'] ?? 0), 'building-lock'],
            ['Tổng tin tuyển dụng', (int) $jobs->sum(), 'briefcase'],
            ['Tin chờ duyệt', Job::whereHas('versions', fn ($query) => $query->where('review_status', 'pending'))->count(), 'hourglass-split'],
            ['Tin công khai', app(PublicJobService::class)->query()->count(), 'file-earmark-text'],
        ];
        foreach (['paused' => 'tạm dừng', 'closed' => 'đã đóng', 'blocked' => 'bị khóa'] as $status => $label) {
            $systemMetrics[] = ['Tin '.$label, (int) ($jobs[$status] ?? 0), 'file-earmark-text'];
        }
        $applicationMetrics = [['Tổng hồ sơ', (int) $applications->sum(), 'files']];
        foreach (array_intersect_key(app(CandidateCatalog::class)->statuses(), RecruiterApplicationService::TRANSITIONS) as $status => $label) {
            $applicationMetrics[] = [$label, (int) ($applications[$status] ?? 0), 'person-lines-fill'];
        }
        $dailyJobs = $this->daily(Job::query(), 'created_at', $start, $end);
        $dailyApplications = $this->daily(Application::query(), 'applied_at', $start, $end);
        $trend = [];
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $day = $date->toDateString();
            $trend[] = ['date' => $day, 'jobs' => (int) ($dailyJobs[$day] ?? 0), 'applications' => (int) ($dailyApplications[$day] ?? 0)];
        }

        return compact('userMetrics', 'systemMetrics', 'applicationMetrics', 'trend') + ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    private function daily($query, string $column, CarbonImmutable $start, CarbonImmutable $end)
    {
        // DATETIME lưu UTC; nhóm ngày Việt Nam bằng offset, không phụ thuộc bảng timezone của MariaDB.
        return $query->where($column, '>=', $start->utc())->where($column, '<', $end->addDay()->utc())
            ->selectRaw("DATE(DATE_ADD($column, INTERVAL 7 HOUR)) AS day, COUNT(*) AS total")
            ->groupBy('day')->pluck('total', 'day');
    }
}
