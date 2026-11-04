<?php

use App\Http\Controllers\RecruiterPageController;
use Illuminate\Support\Facades\Route;

$recruiterPages = [
    '' => ['dashboard', 'Tổng quan tuyển dụng', 'dashboard'],
    '/doanh-nghiep' => ['company', 'Hồ sơ doanh nghiệp', 'company'],
    '/doanh-nghiep/tao' => ['company-form', 'Tạo hồ sơ doanh nghiệp', 'company'],
    '/doanh-nghiep/chinh-sua' => ['company-form', 'Chỉnh sửa doanh nghiệp', 'company'],
    '/tin-tuyen-dung' => ['jobs', 'Quản lý tin tuyển dụng', 'jobs'],
    '/tin-tuyen-dung/tao' => ['job-form', 'Tạo tin tuyển dụng', 'jobs'],
    '/tin-tuyen-dung/chinh-sua' => ['job-form', 'Chỉnh sửa tin tuyển dụng', 'jobs'],
    '/tin-tuyen-dung/chi-tiet' => ['job-detail', 'Chi tiết tin tuyển dụng', 'jobs'],
    '/ho-so' => ['applications', 'Hồ sơ ứng tuyển', 'applications'],
    '/ho-so/chi-tiet' => ['application-detail', 'Chi tiết hồ sơ ứng tuyển', 'applications'],
    '/thong-bao' => ['notifications', 'Thông báo tuyển dụng', 'notifications'],
    '/tai-khoan' => ['account', 'Tài khoản nhà tuyển dụng', 'account'],
];

foreach ($recruiterPages as $path => [$page, $title, $section]) {
    $name = match ($path) {
        '' => 'dashboard',
        '/doanh-nghiep/tao' => 'company-create',
        '/doanh-nghiep/chinh-sua' => 'company-edit',
        '/tin-tuyen-dung/tao' => 'job-create',
        '/tin-tuyen-dung/chinh-sua' => 'job-edit',
        default => $page,
    };
    Route::get('/nha-tuyen-dung/quan-ly'.$path, [RecruiterPageController::class, 'show'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->defaults('section', $section)
        ->name('recruiter.'.$name);
}
