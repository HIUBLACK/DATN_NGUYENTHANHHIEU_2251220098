<?php

use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/viec-lam', [FrontendController::class, 'jobs'])->name('jobs');
Route::get('/viec-lam/{id}', [FrontendController::class, 'job'])->whereNumber('id')->name('jobs.show');
foreach (['cong-ty' => 'companies', 'gioi-thieu' => 'about', 'nha-tuyen-dung' => 'employer'] as $uri => $page) {
    Route::get('/'.$uri, [FrontendController::class, 'page'])->defaults('page', $page)->name($page);
}
foreach (['dang-nhap' => 'login', 'dang-ky' => 'register', 'quen-mat-khau' => 'forgot', 'dat-lai-mat-khau' => 'reset'] as $uri => $mode) {
    Route::get('/'.$uri, [FrontendController::class, 'auth'])->defaults('mode', $mode)->name($mode);
}
// UI preview only: these pages expose no private account data or mutation endpoints.
foreach (['tong-quan' => 'ca.home', 'cv' => 'resumes', 'ky-nang' => 'skills', 'goi-y-ai' => 'recommendations', 'da-luu' => 'saved', 'ung-tuyen' => 'applications', 'thong-bao' => 'notifications', 'ho-so' => 'profile', 'cai-dat' => 'settings'] as $uri => $page) {
    Route::get('/ung-vien/'.$uri, [FrontendController::class, 'candidate'])->defaults('page', $page)->name('candidate.'.$page);
}
