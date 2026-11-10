<?php

use App\Http\Controllers\AdminPageController;
use Illuminate\Support\Facades\Route;

$adminPages = [
    '' => ['dashboard', 'Tổng quan hệ thống', 'dashboard'],
    'nguoi-dung' => ['users.index', 'Quản lý người dùng', 'users'],
    'nguoi-dung/chi-tiet' => ['users.show', 'Chi tiết tài khoản', 'users'],
    'nguoi-dung/lich-su' => ['users.history', 'Lịch sử khóa/mở khóa', 'users'],
    'doanh-nghiep' => ['companies.index', 'Quản lý doanh nghiệp', 'companies'],
    'doanh-nghiep/chi-tiet' => ['companies.show', 'Chi tiết doanh nghiệp', 'companies'],
    'tin-tuyen-dung' => ['jobs.index', 'Quản lý tin tuyển dụng', 'jobs'],
    'kiem-duyet' => ['jobs.queue', 'Tin tuyển dụng chờ duyệt', 'queue'],
    'tin-tuyen-dung/chi-tiet' => ['jobs.show', 'Chi tiết và phiên bản tin', 'jobs'],
    'tin-tuyen-dung/lich-su' => ['jobs.history', 'Lịch sử kiểm duyệt', 'jobs'],
    'ung-tuyen' => ['applications.index', 'Giám sát ứng tuyển', 'applications'],
    'ung-tuyen/chi-tiet' => ['applications.show', 'Lịch sử xử lý hồ sơ', 'applications'],
    'danh-muc/nhom-nghe' => ['occupations.index', 'Nhóm nghề CNTT', 'occupations'],
    'danh-muc/chuyen-mon' => ['specialties.index', 'Chuyên môn CNTT', 'specialties'],
    'tai-khoan' => ['account', 'Tài khoản quản trị', 'account'],
];

foreach ($adminPages as $path => [$page, $title, $section]) {
    Route::get('/admin'.($path ? '/'.$path : ''), [AdminPageController::class, 'show'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->defaults('section', $section)
        ->name('admin.'.$page);
}
