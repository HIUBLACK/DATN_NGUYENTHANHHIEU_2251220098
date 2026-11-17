<?php

use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\RecruiterPageController;
use Illuminate\Support\Facades\Route;

$recruiterPages = [
    '' => ['dashboard', 'Tổng quan tuyển dụng', 'dashboard'],
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

Route::post('/nha-tuyen-dung/ho-so-doanh-nghiep', [CompanyProfileController::class, 'store'])
    ->middleware('throttle:20,1')->block()->name('recruiter.company-store');
Route::put('/nha-tuyen-dung/ho-so-doanh-nghiep/{company}', [CompanyProfileController::class, 'update'])
    ->middleware('throttle:20,1')->whereUuid('company')->block()->name('recruiter.company-update');

Route::get('/nha-tuyen-dung/quan-ly/doanh-nghiep', [CompanyProfileController::class, 'show'])->name('recruiter.company');
Route::get('/nha-tuyen-dung/quan-ly/doanh-nghiep/tao', [CompanyProfileController::class, 'create'])->name('recruiter.company-create');
Route::get('/nha-tuyen-dung/quan-ly/doanh-nghiep/chinh-sua', [CompanyProfileController::class, 'edit'])->name('recruiter.company-edit');
Route::get('/nha-tuyen-dung/ho-so-doanh-nghiep/{company}/anh/{slot}', [CompanyProfileController::class, 'privateMedia'])
    ->whereUuid('company')->where('slot', 'logo|cover|office-[0-5]')->name('recruiter.company-media');
