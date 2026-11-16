<?php

namespace App\Http\Requests\Auth;

use App\Services\GooglePasswordSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class GooglePasswordSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GooglePasswordSetupService::eligible($this->user());
    }

    protected function getRedirectUrl(): string
    {
        return route(GooglePasswordSetupService::accountRoute($this->user()));
    }

    public function rules(): array
    {
        return ['password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()]];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.letters' => 'Mật khẩu cần có chữ cái.',
            'password.numbers' => 'Mật khẩu cần có chữ số.',
            'password.max' => 'Mật khẩu không được vượt quá 255 ký tự.',
        ];
    }
}
