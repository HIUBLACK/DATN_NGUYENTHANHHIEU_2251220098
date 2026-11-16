<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    protected function getRedirectUrl(): string
    {
        $token = $this->input('token');
        if (is_string($token) && preg_match('/^[A-Za-z0-9]{1,255}$/D', $token)) {
            return route('password.reset', ['token' => $token]);
        }

        return route('password.request');
    }

    public function rules(): array
    {
        return parent::rules() + [
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'token.required' => 'Liên kết đặt lại mật khẩu không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.letters' => 'Mật khẩu cần có chữ cái.',
            'password.numbers' => 'Mật khẩu cần có chữ số.',
            'password.max' => 'Mật khẩu không được vượt quá 255 ký tự.',
        ];
    }
}
