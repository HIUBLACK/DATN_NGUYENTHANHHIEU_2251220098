<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminStatusRequest;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\AdminManagementService;
use App\Services\AdminStatusService;
use App\Services\CompanyService;
use App\Services\RecruiterApplicationService;
use App\Support\CandidateCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminManagementController extends Controller
{
    public function show(Request $request, AdminManagementService $management, AdminDashboardService $dashboard)
    {
        Gate::authorize('manage-system');
        $page = $request->route('page');
        $section = $request->route('section');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'role' => [Rule::excludeIf($section !== 'users'), 'nullable', 'in:candidate,recruiter'],
            'status' => ['nullable', $section === 'applications' ? 'in:applied,reviewing,interview,hired,rejected,withdrawn' : 'in:active,locked'],
            'candidate' => ['nullable', 'string', 'max:150'], 'company' => ['nullable', 'string', 'max:150'],
            'recruiter' => ['nullable', 'string', 'max:150'], 'job' => ['nullable', 'string', 'max:150'],
            'start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $data = [];
        if ($page === 'dashboard') {
            $first = CarbonImmutable::parse($filters['start'] ?? now('Asia/Ho_Chi_Minh')->subDays(29)->toDateString());
            $last = CarbonImmutable::parse($filters['end'] ?? now('Asia/Ho_Chi_Minh')->toDateString());
            if ($last < $first || $first->diffInDays($last) > 365) {
                throw ValidationException::withMessages(['end' => 'Chọn khoảng ngày hợp lệ, tối đa 366 ngày.']);
            }
            $data = $dashboard->data($filters);
        } elseif (str_ends_with($page, '.index')) {
            $data['records'] = $management->listing($section, $filters);
        } else {
            abort_unless($request->filled('id'), 404);
            $id = $request->validate(['id' => ['required', $section === 'applications' ? 'uuid' : 'integer']])['id'];
            $record = match ($section) {
                'users' => User::whereIn('role', ['candidate', 'recruiter'])->with(['candidateProfile', 'company'])->findOrFail($id),
                'companies' => Company::with(['recruiter'])->withCount('jobs')->findOrFail($id),
                'applications' => Application::with(['candidate', 'job.company.recruiter', 'jobVersion'])->where('public_id', $id)->firstOrFail(),
            };
            if ($section === 'applications') {
                Gate::authorize('view', $record);
                $data['history'] = $record->statusHistories()->with('actor')->orderBy('id')->get();
            } else {
                $record->loadMax('activityLogs', 'id');
                $data['history'] = $record->activityLogs()->with('actor')->latest('id')->paginate(15)->withQueryString();
            }
            if ($section === 'companies') {
                Gate::authorize('moderate', $record);
                $profile = app(CompanyService::class)->publicData($record, true);
                $profile['logo_url'] = $record->logo_path ? route('admin.company-media', [$record->public_id, 'logo']) : null;
                $profile['gallery_urls'] = [];
                foreach ($record->office_images ?? [] as $index => $image) {
                    $profile['gallery_urls'][] = route('admin.company-media', [$record->public_id, 'office-'.$index]);
                }
                $data['profile'] = $profile;
            }
            $data['record'] = $record;
        }

        return view('Admin.ad_pages.'.$page, $data + [
            'page' => $page, 'section' => $section, 'title' => $request->route('title'),
            'realManagement' => true, 'seed' => [],
            'statusLabels' => array_intersect_key(app(CandidateCatalog::class)->statuses(), RecruiterApplicationService::TRANSITIONS)
                + ['active' => 'Hoạt động', 'locked' => 'Bị khóa'],
        ]);
    }

    public function status(AdminStatusRequest $request, string $type, int $id, AdminStatusService $service)
    {
        $record = $type === 'users' ? User::findOrFail($id) : Company::findOrFail($id);
        $service->change($request->user(), $record, $request->validated());

        return back()->with('notice', 'Đã cập nhật trạng thái và lưu lịch sử thao tác.');
    }
}
