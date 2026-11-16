<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PasswordRecoveryController extends Controller
{
    public function __construct(private PasswordRecoveryService $passwords) {}

    public function page(Request $request, CandidatePageController $pages): Response
    {
        return response($pages->page($request))
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function send(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->send($request->validated('email'));

        return redirect()->route('password.request')->with('notice', PasswordRecoveryService::MESSAGE);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        if (! $this->passwords->reset($request->validated(), $request)) {
            return redirect()->route('password.request')->withErrors(['email' => 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.']);
        }

        return redirect()->route('login')->with('notice', 'Đã đặt lại mật khẩu và thu hồi phiên cũ. Vui lòng đăng nhập lại.');
    }
}
