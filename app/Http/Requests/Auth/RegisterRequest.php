<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(['candidate', 'recruiter'])],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng điền :attribute.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.letters' => 'Mật khẩu cần có chữ cái.',
            'password.numbers' => 'Mật khẩu cần có chữ số.',
            'role.in' => 'Chỉ được đăng ký ứng viên hoặc nhà tuyển dụng.',
        ];
    }
}
