<?php

use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminJobModerationController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminPageController;
use App\Http\Controllers\CompanyProfileController;
use App\Support\AdminCategory;
use Illuminate\Support\Facades\Route;

$adminPages = [
    '' => ['dashboard', 'Tổng quan hệ thống', 'dashboard'],
    'nguoi-dung' => ['users.index', 'Quản lý người dùng', 'users'],
    'nguoi-dung/chi-tiet' => ['users.show', 'Chi tiết tài khoản', 'users'],
    'nguoi-dung/lich-su' => ['users.history', 'Lịch sử khóa/mở khóa', 'users'],
    'doanh-nghiep' => ['companies.index', 'Quản lý doanh nghiệp', 'companies'],
    'doanh-nghiep/chi-tiet' => ['companies.show', 'Chi tiết doanh nghiệp', 'companies'],
    'tin-tuyen-dung' => ['jobs.index', 'Quản lý tin tuyển dụng', 'jobs'],
    'tin-tuyen-dung/chi-tiet' => ['jobs.show', 'Chi tiết và phiên bản tin', 'jobs'],
    'tin-tuyen-dung/lich-su' => ['jobs.history', 'Lịch sử kiểm duyệt', 'jobs'],
    'ung-tuyen' => ['applications.index', 'Giám sát ứng tuyển', 'applications'],
    'ung-tuyen/chi-tiet' => ['applications.show', 'Lịch sử xử lý hồ sơ', 'applications'],
    'tai-khoan' => ['account', 'Tài khoản quản trị', 'account'],
];

foreach ($adminPages as $path => [$page, $title, $section]) {
    $controller = in_array($section, ['dashboard', 'users', 'companies', 'applications'], true) ? AdminManagementController::class : AdminPageController::class;
    Route::get('/admin'.($path ? '/'.$path : ''), [$controller, 'show'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->defaults('section', $section)
        ->name('admin.'.$page);
}

Route::patch('/admin/quan-ly/{type}/{id}/trang-thai', [AdminManagementController::class, 'status'])
    ->whereIn('type', ['users', 'companies'])->whereNumber('id')->middleware('throttle:30,1')->name('admin.management.status');
Route::get('/admin/doanh-nghiep/{company}/anh/{slot}', [CompanyProfileController::class, 'adminMedia'])
    ->whereUuid('company')->where('slot', 'logo|office-[0-5]')->name('admin.company-media');

foreach (AdminCategory::TYPES as $type => [$model, $title, $path]) {
    Route::prefix('admin/danh-muc/'.$path)->name('admin.'.$type.'.')->group(function () use ($type) {
        $controller = AdminCategoryController::class;
        Route::get('/', [$controller, 'index'])->defaults('categoryType', $type)->name('index');
        Route::get('/tao', [$controller, 'form'])->defaults('categoryType', $type)->name('create');
        Route::get('/{item}/chinh-sua', [$controller, 'form'])->whereNumber('item')->defaults('categoryType', $type)->name('edit');
        Route::post('/', [$controller, 'store'])->defaults('categoryType', $type)->middleware('throttle:30,1')->name('store');
        Route::put('/{item}', [$controller, 'update'])->whereNumber('item')->defaults('categoryType', $type)->middleware('throttle:30,1')->name('update');
        Route::patch('/{item}/trang-thai', [$controller, 'status'])->whereNumber('item')->defaults('categoryType', $type)->middleware('throttle:30,1')->name('status');
    });
}

Route::get('/admin/kiem-duyet', [AdminJobModerationController::class, 'index'])->name('admin.jobs.queue');
Route::get('/admin/kiem-duyet/{version}', [AdminJobModerationController::class, 'show'])->whereNumber('version')->name('admin.moderation.show');
Route::post('/admin/kiem-duyet/{version}/{action}', [AdminJobModerationController::class, 'decide'])
    ->whereNumber('version')->whereIn('action', ['approve', 'reject'])->middleware('throttle:30,1')->name('admin.moderation.decide');
