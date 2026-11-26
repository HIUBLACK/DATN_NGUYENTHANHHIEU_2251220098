<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminJobStatusRequest;
use App\Services\AdminJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminJobController extends Controller
{
    public function show(Request $request, AdminJobService $jobs)
    {
        Gate::authorize('manage-system');
        $page = $request->route('page');
        if ($page === 'jobs.index') {
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:150'],
                'status' => ['nullable', 'in:draft,open,paused,closed,blocked'],
                'review' => ['nullable', 'in:draft,pending,approved,rejected'],
            ]);
            $data = ['records' => $jobs->listing($filters)];
        } else {
            abort_unless($request->filled('id'), 404);
            $input = $request->validate(['id' => ['required', 'integer', 'min:1'], 'version' => ['nullable', 'integer', 'min:1']]);
            $data = $jobs->detail((int) $input['id'], isset($input['version']) ? (int) $input['version'] : null);
        }

        return view('Admin.ad_pages.'.$page, $data + [
            'page' => $page, 'section' => 'jobs', 'title' => $request->route('title'),
            'realManagement' => true, 'seed' => [],
        ]);
    }

    public function status(AdminJobStatusRequest $request, int $job, AdminJobService $jobs)
    {
        $jobs->changeStatus($request->user(), $job, $request->validated());

        return back()->with('notice', 'Đã cập nhật trạng thái tin và lưu lịch sử thao tác.');
    }
}
