<?php

use App\Http\Controllers\CandidateDemoController;
use App\Http\Controllers\CandidatePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CandidatePageController::class, 'home'])->name('home');
Route::get('/viec-lam', [CandidatePageController::class, 'jobs'])->name('jobs');
Route::get('/viec-lam/{id}', [CandidatePageController::class, 'job'])->whereNumber('id')->name('jobs.show');

$publicPages = [
    'cong-ty' => ['companies', 'Công ty'],
    'gioi-thieu' => ['about', 'Giới thiệu'],
    'nha-tuyen-dung' => ['employer', 'Nhà tuyển dụng'],
    'dang-nhap' => ['login', 'Đăng nhập'],
    'dang-ky' => ['register', 'Đăng ký'],
    'quen-mat-khau' => ['forgot', 'Quên mật khẩu'],
    'dat-lai-mat-khau' => ['reset', 'Đặt lại mật khẩu'],
];

foreach ($publicPages as $path => [$page, $title]) {
    Route::get('/'.$path, [CandidatePageController::class, 'page'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->name($page);
}

$candidatePages = [
    'cv' => ['resumes', 'Quản lý CV'],
    'ky-nang' => ['skills', 'Xác nhận kỹ năng CV'],
    'goi-y-ai' => ['recommendations', 'Việc làm gợi ý AI'],
    'da-luu' => ['saved', 'Việc làm đã lưu'],
    'ung-tuyen' => ['applications', 'Hồ sơ ứng tuyển'],
    'thong-bao' => ['notifications', 'Thông báo'],
    'tin-nhan' => ['messages', 'Tin nhắn'],
    'ho-so' => ['profile', 'Hồ sơ cá nhân'],
    'cai-dat' => ['settings', 'Cài đặt tài khoản'],
];

foreach ($candidatePages as $path => [$page, $title]) {
    Route::get('/ung-vien/'.$path, [CandidatePageController::class, 'page'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->name('candidate.'.$page);
}

Route::post('/demo/bat-dau', [CandidateDemoController::class, 'enter'])->name('demo.enter');
Route::post('/demo/thoat', [CandidateDemoController::class, 'leave'])->name('demo.leave');
Route::post('/demo/xoa-du-lieu', [CandidateDemoController::class, 'reset'])->name('demo.reset');
Route::post('/demo/tai-khoan/{mode}', [CandidateDemoController::class, 'authPreview'])
    ->whereIn('mode', ['login', 'register', 'forgot', 'reset', 'settings'])
    ->name('demo.auth');
Route::post('/demo/ho-so-mau', [CandidateDemoController::class, 'sampleApplications'])->name('demo.applications');
Route::post('/ung-vien/cv', [CandidateDemoController::class, 'upload'])->name('candidate.upload');
Route::post('/ung-vien/cv/{id}/pdf', [CandidateDemoController::class, 'upload'])->name('candidate.replace');
Route::get('/ung-vien/cv/{id}/pdf', [CandidateDemoController::class, 'pdf'])->name('candidate.pdf');
Route::post('/ung-vien/cv/{id}/{action}', [CandidateDemoController::class, 'resumeAction'])
    ->whereIn('action', ['main', 'rename', 'delete', 'state', 'sample', 'skills'])
    ->name('candidate.resume-action');
Route::post('/ung-vien/luu/{id}', [CandidateDemoController::class, 'save'])->whereNumber('id')->name('candidate.save');
Route::post('/ung-vien/ung-tuyen/{id}', [CandidateDemoController::class, 'apply'])->whereNumber('id')->block()->name('candidate.apply');
Route::post('/ung-vien/rut-ho-so/{id}', [CandidateDemoController::class, 'withdraw'])->block()->name('candidate.withdraw');
Route::post('/ung-vien/thong-bao/{id}', [CandidateDemoController::class, 'readNotification'])->name('candidate.read');
Route::post('/ung-vien/ho-so', [CandidateDemoController::class, 'profile'])->name('candidate.profile-save');
Route::get('/ung-vien/anh-dai-dien', [CandidateDemoController::class, 'avatar'])->name('candidate.avatar');
