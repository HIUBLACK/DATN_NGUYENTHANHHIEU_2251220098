<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Support\CandidateCatalog;

class RecruiterDashboardService
{
    public function data(User $user): array
    {
        $companyId = Company::where('recruiter_id', $user->id)->where('status', 'active')->value('id');
        $jobs = Job::where('company_id', $companyId);
        $counts = (clone $jobs)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all();
        $expired = (clone $jobs)->where('status', 'open')->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '<=', now())
                ->orWhereHas('publishedVersion', fn ($version) => $version->whereDate('application_deadline', '<', now('Asia/Ho_Chi_Minh')->toDateString()));
        })->count();
        $totalJobs = array_sum($counts);
        $counts['open'] = ($counts['open'] ?? 0) - $expired;
        $counts['expired'] = $expired;
        $pending = (clone $jobs)->whereHas('versions', fn ($version) => $version->where('review_status', 'pending'))->count();
        $rejected = (clone $jobs)->whereHas('latestVersion', fn ($version) => $version->where('review_status', 'rejected'))->count();
        $applications = Application::whereIn('job_id', (clone $jobs)->select('id'));
        $applicationCounts = (clone $applications)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all();
        $labels = array_intersect_key(app(CandidateCatalog::class)->statuses(), RecruiterApplicationService::TRANSITIONS);
        $jobMetrics = [
            ['Tổng số tin', $totalJobs, 'briefcase'],
            ['Tin đang mở', $counts['open'], 'broadcast'],
            ['Tin tạm dừng', $counts['paused'] ?? 0, 'pause-circle'],
            ['Tin đã đóng', $counts['closed'] ?? 0, 'archive'],
        ];
        $applicationMetrics = [['Tổng hồ sơ', array_sum($applicationCounts), 'people']];
        foreach (['applied', 'reviewing', 'interview', 'hired', 'rejected'] as $status) {
            $applicationMetrics[] = [$labels[$status], $applicationCounts[$status] ?? 0, 'person-check'];
        }

        return [
            'jobMetrics' => $jobMetrics, 'applicationMetrics' => $applicationMetrics,
            'dashboardCounts' => [
                'jobs' => $counts, 'jobTotal' => $totalJobs, 'pending' => $pending, 'rejected' => $rejected,
                'applications' => $applicationCounts, 'applicationTotal' => array_sum($applicationCounts),
            ],
            'recentApplications' => (clone $applications)->with(['candidate', 'jobVersion'])->latest('applied_at')->latest('id')->limit(5)->get(),
            'applicationLabels' => $labels,
        ];
    }
}
