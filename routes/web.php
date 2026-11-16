<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateDemoController;
use App\Http\Controllers\CandidatePageController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\RecruiterPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CandidatePageController::class, 'home'])->name('home');
Route::get('/viec-lam', [CandidatePageController::class, 'jobs'])->name('jobs');
Route::get('/viec-lam/{id}', [CandidatePageController::class, 'job'])->whereNumber('id')->name('jobs.show');

$publicPages = [
    'cong-ty' => ['companies', 'Công ty'],
    'gioi-thieu' => ['about', 'Giới thiệu'],
    'login' => ['login', 'Đăng nhập'],
    'register' => ['register', 'Đăng ký'],
    'quen-mat-khau' => ['forgot', 'Quên mật khẩu'],
    'dat-lai-mat-khau' => ['reset', 'Đặt lại mật khẩu'],
];

foreach ($publicPages as $path => [$page, $title]) {
    Route::get('/'.$path, [CandidatePageController::class, 'page'])
        ->defaults('page', $page)
        ->defaults('title', $title)
        ->middleware(in_array($page, ['login', 'register', 'forgot', 'reset']) ? ['guest'] : [])
        ->name($page);
}

Route::redirect('/dang-nhap', '/login');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware(['guest', 'throttle:10,1'])->block()->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware(['auth.session', 'throttle:20,1'])->block()->name('google.callback');
Route::get('/register/google', [GoogleAuthController::class, 'registration'])
    ->defaults('page', 'google-register')->defaults('title', 'Hoàn tất đăng ký Google')
    ->middleware('guest')->name('google.register');
Route::post('/register/google', [GoogleAuthController::class, 'complete'])
    ->middleware(['guest', 'throttle:register'])->block()->name('google.complete');
Route::post('/account/google/link', [GoogleAuthController::class, 'link'])
    ->middleware(['auth', 'auth.session', 'throttle:5,1'])->block()->name('google.link');
Route::redirect('/dang-ky', '/register');
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:register'])->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:login'])->name('auth.login');
Route::post('/dang-xuat', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/tai-khoan/doi-mat-khau', [AuthController::class, 'password'])
    ->middleware(['auth', 'auth.session', 'throttle:5,1'])->name('password.change');

Route::middleware(['auth', 'auth.session', 'role:candidate'])->group(function () {
    $candidatePages = [
        'cv' => ['resumes', 'Quản lý CV'],
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
    Route::post('/demo/ho-so-mau', [CandidateDemoController::class, 'sampleApplications'])->name('demo.applications');
    Route::post('/ung-vien/cv', [CandidateDemoController::class, 'upload'])->name('candidate.upload');
    Route::post('/ung-vien/cv/{id}/pdf', [CandidateDemoController::class, 'upload'])->name('candidate.replace');
    Route::get('/ung-vien/cv/{id}/pdf', [CandidateDemoController::class, 'pdf'])->name('candidate.pdf');
    Route::post('/ung-vien/cv/{id}/{action}', [CandidateDemoController::class, 'resumeAction'])
        ->whereIn('action', ['main', 'rename', 'delete', 'state', 'sample'])
        ->name('candidate.resume-action');
    Route::post('/ung-vien/luu/{id}', [CandidateDemoController::class, 'save'])->whereNumber('id')->name('candidate.save');
    Route::post('/ung-vien/ung-tuyen/{id}', [CandidateDemoController::class, 'apply'])->whereNumber('id')->block()->name('candidate.apply');
    Route::post('/ung-vien/rut-ho-so/{id}', [CandidateDemoController::class, 'withdraw'])->block()->name('candidate.withdraw');
    Route::post('/ung-vien/thong-bao/{id}', [CandidateDemoController::class, 'readNotification'])->name('candidate.read');
    Route::post('/ung-vien/ho-so', [CandidateDemoController::class, 'profile'])->name('candidate.profile-save');
    Route::get('/ung-vien/anh-dai-dien', [CandidateDemoController::class, 'avatar'])->name('candidate.avatar');
});

Route::middleware(['auth', 'auth.session', 'role:recruiter'])->group(function () {
    require __DIR__.'/recruiter.php';
});

Route::get('/nha-tuyen-dung', [RecruiterPageController::class, 'show'])
    ->defaults('page', 'dashboard')
    ->defaults('title', 'Tổng quan tuyển dụng')
    ->defaults('section', 'dashboard')
    ->middleware(['auth', 'auth.session', 'role:recruiter'])
    ->name('employer');

Route::middleware(['auth', 'auth.session', 'role:admin'])->group(function () {
    require __DIR__.'/admin.php';
});

Route::get('/cong-ty/mau/{id}', [CandidatePageController::class, 'demoCompany'])->whereNumber('id')->name('companies.demo');
Route::get('/cong-ty/{company}', [CandidatePageController::class, 'company'])->whereUuid('company')->name('companies.show');
Route::get('/cong-ty/{company}/anh/{slot}', [CompanyProfileController::class, 'media'])
    ->whereUuid('company')->where('slot', 'logo|cover|office-[0-5]')->name('companies.media');
