<?php

use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\RecruiterApplicationController;
use App\Http\Controllers\RecruiterJobController;
use App\Http\Controllers\RecruiterPageController;
use Illuminate\Support\Facades\Route;

$recruiterPages = [
    '' => ['dashboard', 'Tổng quan tuyển dụng', 'dashboard'],
    '/thong-bao' => ['notifications', 'Thông báo tuyển dụng', 'notifications'],
    '/tai-khoan' => ['account', 'Tài khoản nhà tuyển dụng', 'account'],
];

Route::post('/nha-tuyen-dung/quan-ly/thong-bao/{id}/da-doc', [RecruiterPageController::class, 'readNotification'])
    ->where('id', 'all|[a-fA-F0-9-]{36}')->name('recruiter.notification-read');

Route::get('/nha-tuyen-dung/quan-ly/ho-so', [RecruiterApplicationController::class, 'index'])->name('recruiter.applications');
Route::get('/nha-tuyen-dung/quan-ly/ho-so/chi-tiet', [RecruiterApplicationController::class, 'show'])->name('recruiter.application-detail');
Route::post('/nha-tuyen-dung/quan-ly/ho-so/{id}/danh-gia-ai', [RecruiterApplicationController::class, 'evaluate'])
    ->whereUuid('id')->middleware('throttle:10,1')->block()->name('recruiter.application-evaluate');
Route::get('/nha-tuyen-dung/quan-ly/ho-so/{id}/pdf', [RecruiterApplicationController::class, 'snapshot'])->whereUuid('id')->name('recruiter.application-pdf');
Route::post('/nha-tuyen-dung/quan-ly/ho-so/{id}/trang-thai', [RecruiterApplicationController::class, 'transition'])
    ->whereUuid('id')->middleware('throttle:30,1')->block()->name('recruiter.application-status');
Route::post('/nha-tuyen-dung/quan-ly/ho-so/{id}/ghi-chu', [RecruiterApplicationController::class, 'note'])
    ->whereUuid('id')->middleware('throttle:30,1')->block()->name('recruiter.application-note');

foreach ($recruiterPages as $path => [$page, $title, $section]) {
    $name = match ($path) {
        '' => 'dashboard',
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

Route::get('/nha-tuyen-dung/quan-ly/tin-tuyen-dung', [RecruiterJobController::class, 'index'])->name('recruiter.jobs');
Route::get('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/tao', [RecruiterJobController::class, 'create'])->block()->name('recruiter.job-create');
Route::post('/nha-tuyen-dung/quan-ly/tin-tuyen-dung', [RecruiterJobController::class, 'store'])->middleware('throttle:20,1')->block()->name('recruiter.job-store');
Route::get('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}', [RecruiterJobController::class, 'show'])->whereNumber('job')->name('recruiter.job-detail');
Route::get('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}/chinh-sua', [RecruiterJobController::class, 'edit'])->whereNumber('job')->name('recruiter.job-edit');
Route::get('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}/xem-truoc', [RecruiterJobController::class, 'show'])->whereNumber('job')->name('recruiter.job-preview');
Route::put('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}', [RecruiterJobController::class, 'update'])->whereNumber('job')->middleware('throttle:30,1')->block()->name('recruiter.job-update');
Route::post('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}/gui-duyet', [RecruiterJobController::class, 'submit'])->whereNumber('job')->middleware('throttle:10,1')->block()->name('recruiter.job-submit');
Route::post('/nha-tuyen-dung/quan-ly/tin-tuyen-dung/{job}/{action}', [RecruiterJobController::class, 'transition'])
    ->whereNumber('job')->whereIn('action', ['pause', 'reopen', 'close'])->middleware('throttle:20,1')->block()->name('recruiter.job-transition');
