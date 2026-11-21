<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecruiterApplicationRequest;
use App\Models\Job;
use App\Services\RecruiterApplicationService;
use App\Support\CandidateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RecruiterApplicationController extends Controller
{
    public function __construct(private RecruiterApplicationService $applications) {}

    public function index(Request $request)
    {
        $query = $this->applications->owned($request->user())->with(['candidate', 'jobVersion']);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 150);
        if ($search !== '') {
            $query->whereHas('candidate', fn ($candidate) => $candidate->where('name', 'like', '%'.$search.'%'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('jobId')) {
            $query->where('job_id', (int) $request->query('jobId'));
        }
        $jobs = Job::whereHas('company', fn ($company) => $company->where('recruiter_id', $request->user()->id)->where('status', 'active'))
            ->with('latestVersion')->orderByDesc('id')->get();

        return view('employer.applications', $this->layout('applications', 'Hồ sơ ứng tuyển') + [
            'applications' => $query->orderByDesc('applied_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'jobs' => $jobs,
        ]);
    }

    public function show(Request $request)
    {
        $application = $this->applications->owned($request->user())->where('public_id', (string) $request->query('id'))
            ->with(['candidate.candidateProfile', 'jobVersion', 'statusHistories.actor', 'notes.recruiter'])->firstOrFail();
        Gate::authorize('view', $application);
        Gate::authorize('viewNotes', $application);

        return view('employer.application-detail', $this->layout('application-detail', 'Chi tiết hồ sơ ứng tuyển') + [
            'application' => $application,
            'nextStatuses' => RecruiterApplicationService::TRANSITIONS[$application->status->value],
        ]);
    }

    public function snapshot(Request $request, string $id)
    {
        $application = $this->applications->owned($request->user())->where('public_id', $id)->firstOrFail();
        Gate::authorize('viewSnapshot', $application);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($application->resume_snapshot_path), 404);
        $headers = [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ];
        if ($request->boolean('download')) {
            return $disk->download($application->resume_snapshot_path, $application->resume_snapshot_filename, $headers);
        }

        return $disk->response($application->resume_snapshot_path, $application->resume_snapshot_filename, $headers);
    }

    public function transition(RecruiterApplicationRequest $request, string $id)
    {
        $this->applications->changeStatus($request->user(), $id, $request->validated());

        return redirect()->route('recruiter.application-detail', ['id' => $id])->with('job_notice', 'Đã cập nhật trạng thái hồ sơ.');
    }

    public function note(RecruiterApplicationRequest $request, string $id)
    {
        $this->applications->saveNote($request->user(), $id, $request->validated());

        return redirect()->route('recruiter.application-detail', ['id' => $id])->with('job_notice', 'Đã lưu ghi chú nội bộ.');
    }

    private function layout(string $page, string $title): array
    {
        return [
            'page' => $page, 'title' => $title, 'section' => 'applications', 'seed' => [],
            'realApplications' => true,
            'statusLabels' => array_intersect_key(app(CandidateCatalog::class)->statuses(), RecruiterApplicationService::TRANSITIONS),
            'statusTones' => ['applied' => 'pending', 'reviewing' => 'pending', 'interview' => 'pending', 'hired' => 'success', 'rejected' => 'failed', 'withdrawn' => 'failed'],
        ];
    }
}
