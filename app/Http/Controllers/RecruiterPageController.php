<?php

namespace App\Http\Controllers;

use App\Notifications\RecruiterApplicationNotice;
use App\Services\RecruiterDashboardService;
use App\Support\RecruiterCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecruiterPageController extends Controller
{
    public function __construct(private RecruiterCatalog $catalog) {}

    public function show(Request $request): View
    {
        $page = $request->route()->defaults['page'];
        $title = $request->route()->defaults['title'];
        $section = $request->route()->defaults['section'];

        $realOverview = in_array($page, ['dashboard', 'notifications'], true);
        $seed = $realOverview ? [] : $this->catalog->data();
        $data = [];
        if ($page === 'dashboard') {
            $data = app(RecruiterDashboardService::class)->data($request->user());
        } elseif ($page === 'notifications') {
            $filters = $request->validate(['filter' => ['nullable', 'in:all,unread,applied,withdrawn']]);
            $notifications = $request->user()->notifications()->where('type', RecruiterApplicationNotice::class);
            $filter = $filters['filter'] ?? 'all';
            if ($filter === 'unread') {
                $notifications->whereNull('read_at');
            } elseif (in_array($filter, ['applied', 'withdrawn'], true)) {
                $notifications->where('data->status', $filter);
            }
            $data['notifications'] = $notifications->latest()->orderByDesc('id')->paginate(15)->withQueryString();
            $data['unreadCount'] = $request->user()->unreadNotifications()->where('type', RecruiterApplicationNotice::class)->count();
        }

        return view('employer.'.$page, [
            'page' => $page,
            'title' => $title,
            'section' => $section,
            'seed' => $seed,
            'realOverview' => $realOverview,
        ] + $data);
    }

    public function readNotification(Request $request, string $id)
    {
        $query = $request->user()->notifications()->where('type', RecruiterApplicationNotice::class);
        if ($id === 'all') {
            $query->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            $query->whereKey($id)->firstOrFail()->markAsRead();
        }

        return back()->with('job_notice', 'Đã đánh dấu thông báo đã đọc.');
    }
}
