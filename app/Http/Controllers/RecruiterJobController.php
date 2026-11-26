<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\SubmitJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Services\RecruiterJobService;
use App\Support\JobInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecruiterJobController extends Controller
{
    public function __construct(private RecruiterJobService $jobs) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'activity' => ['nullable', 'in:'.implode(',', array_keys(JobInput::STATUSES))],
            'moderation' => ['nullable', 'in:'.implode(',', array_keys(JobInput::REVIEWS))],
        ]);
        $jobs = Job::with('latestVersion', 'publishedVersion')->whereHas('company', fn ($query) => $query->where('recruiter_id', $request->user()->id));
        if (! empty($filters['q'])) {
            $jobs->whereHas('latestVersion', fn ($query) => $query->where('title', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'));
        }
        if (! empty($filters['activity'])) {
            $jobs->where('status', $filters['activity']);
        }
        if (! empty($filters['moderation'])) {
            $jobs->whereHas('latestVersion', fn ($query) => $query->where('review_status', $filters['moderation']));
        }

        return view('employer.jobs', $this->layout('jobs', 'Quản lý tin tuyển dụng') + [
            'jobs' => $jobs->latest('id')->paginate(10)->withQueryString(),
            'canCreateJob' => Gate::allows('create', Job::class),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Job::class);
        $key = (string) Str::uuid();
        $tokens = array_slice($request->session()->get('job_creation_keys', []), -9, null, true);
        $tokens[$key] = null;
        $request->session()->put('job_creation_keys', $tokens);

        return view('employer.job-form', $this->layout('job-form', 'Tạo tin tuyển dụng') + [
            'job' => null, 'version' => null, 'form' => [], 'creationKey' => $key,
            'options' => $this->jobs->catalogOptions(), 'baseline' => null,
        ]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $key = $request->validated('creation_key');
        $tokens = $request->session()->get('job_creation_keys', []);
        if (! array_key_exists($key, $tokens)) {
            throw ValidationException::withMessages(['job' => 'Phiên tạo tin đã hết hạn. Vui lòng mở lại form tạo tin.']);
        }
        if ($tokens[$key]) {
            $job = Job::findOrFail($tokens[$key]);
            Gate::authorize('viewManagement', $job);
        } else {
            $job = $request->validated('intent') === 'submit'
                ? $this->jobs->saveForReview($request->user(), $request->validated())
                : $this->jobs->create($request->user(), $request->validated());
            $tokens[$key] = $job->id;
            $request->session()->put('job_creation_keys', $tokens);
        }

        if ($request->validated('intent') === 'submit') {
            return redirect()->route('recruiter.job-detail', $job->id)->with('job_notice', 'Đã gửi phiên bản chờ Admin kiểm duyệt.');
        }

        return redirect()->route('recruiter.job-edit', $job->id)->with('job_notice', 'Đã lưu bản nháp tin tuyển dụng.');
    }

    public function edit(int $job): View
    {
        $record = Job::findOrFail($job);
        Gate::authorize('update', $record);
        $version = $this->jobs->latest($record);

        return view('employer.job-form', $this->layout('job-form', 'Chỉnh sửa tin tuyển dụng') + [
            'job' => $record, 'version' => $version, 'form' => $this->jobs->formData($version),
            'options' => $this->jobs->catalogOptions(), 'baseline' => $this->jobs->reviewBaseline($record),
        ]);
    }

    public function update(UpdateJobRequest $request, int $job): RedirectResponse
    {
        if ($request->validated('intent') === 'submit') {
            $this->jobs->saveForReview($request->user(), $request->validated(), $job);

            return redirect()->route('recruiter.job-detail', $job)->with('job_notice', 'Đã gửi phiên bản chờ Admin kiểm duyệt.');
        }
        $this->jobs->update($request->user(), $job, $request->validated());

        return redirect()->route('recruiter.job-edit', $job)->with('job_notice', 'Đã lưu nội dung nháp. Bản công khai không thay đổi.');
    }

    public function show(int $job): View
    {
        $record = Job::with('company', 'publishedVersion')->findOrFail($job);
        Gate::authorize('viewManagement', $record);

        return view('employer.job-detail', $this->layout('job-detail', 'Chi tiết và xem trước tin tuyển dụng') + [
            'job' => $record, 'version' => $this->jobs->latest($record),
            'versions' => $record->versions()->with('moderationLog')->orderByDesc('version_number')->get(),
        ]);
    }

    public function submit(SubmitJobRequest $request, int $job): RedirectResponse
    {
        $this->jobs->submit($request->user(), $job, $request->validated());

        return redirect()->route('recruiter.job-detail', $job)->with('job_notice', 'Đã gửi phiên bản chờ Admin kiểm duyệt.');
    }

    public function transition(Request $request, int $job, string $action): RedirectResponse
    {
        $this->jobs->transition($request->user(), $job, $action);

        return redirect()->route('recruiter.job-detail', $job)->with('job_notice', 'Đã xử lý trạng thái tin tuyển dụng.');
    }

    private function layout(string $page, string $title): array
    {
        return ['page' => $page, 'title' => $title, 'section' => 'jobs', 'seed' => [], 'realJobs' => true];
    }
}
