<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteGoogleRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user();
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::in(['candidate', 'recruiter'])]];
    }

    public function messages(): array
    {
        return ['role.required' => 'Vui lòng chọn loại tài khoản.', 'role.in' => 'Chỉ được đăng ký ứng viên hoặc nhà tuyển dụng.'];
    }
}
