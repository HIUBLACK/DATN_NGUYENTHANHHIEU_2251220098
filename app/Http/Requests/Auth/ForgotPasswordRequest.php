<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    protected $redirectRoute = 'password.request';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255']];
    }

    public function messages(): array
    {
        return ['email.required' => 'Vui lòng nhập email.', 'email.email' => 'Email không hợp lệ.', 'email.max' => 'Email quá dài.'];
    }
}
