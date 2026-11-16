<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->auth->register($request->validated(), $request);

        return redirect()->route($this->auth->destination($user));
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $this->auth->login($request->validated(), $request);

        return redirect()->route($this->auth->destination($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('login')->with('notice', 'Đã đăng xuất.');
    }

    public function password(ChangePasswordRequest $request): RedirectResponse
    {
        $this->auth->changePassword($request->user(), $request->validated(), $request);

        return redirect()->route('login')->with('notice', 'Đã đổi mật khẩu và thu hồi các phiên đăng nhập. Vui lòng đăng nhập lại.');
    }
}
