<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModerateJobRequest;
use App\Models\JobVersion;
use App\Services\JobModerationService;
use App\Support\JobInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminJobModerationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:'.implode(',', array_keys(JobInput::STATUSES))]]);
        $query = JobVersion::with('job.company')->where('review_status', 'pending');
        if ($filters['q'] ?? null) {
            $term = '%'.addcslashes(trim($filters['q']), '%_\\').'%';
            $query->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)->orWhereHas('job.company', fn ($company) => $company->where('name', 'like', $term));
            });
        }
        if ($filters['status'] ?? null) {
            $query->whereHas('job', fn ($job) => $job->where('status', $filters['status']));
        }

        return view('Admin.ad_pages.moderation.index', $this->layout() + ['versions' => $query->orderBy('submitted_at')->orderBy('id')->paginate(15)->withQueryString(), 'filters' => $filters]);
    }

    public function show(int $version)
    {
        $version = JobVersion::with(['job.company.recruiter', 'job.publishedVersion.skillRequirements', 'job.publishedVersion', 'skillRequirements', 'moderationLog.admin'])->findOrFail($version);
        Gate::authorize('moderate', $version->job);

        return view('Admin.ad_pages.moderation.show', $this->layout() + ['version' => $version, 'published' => $version->job->publishedVersion]);
    }

    public function decide(ModerateJobRequest $request, JobModerationService $service)
    {
        $service->decide($request->user(), (int) $request->route('version'), $request->route('action'), $request->validated());

        return redirect()->route('admin.moderation.show', $request->route('version'))->with('moderation_notice', 'Đã lưu quyết định kiểm duyệt.');
    }

    private function layout(): array
    {
        return ['title' => 'Kiểm duyệt tin tuyển dụng', 'section' => 'queue', 'page' => 'moderation', 'realModeration' => true, 'seed' => []];
    }
}
