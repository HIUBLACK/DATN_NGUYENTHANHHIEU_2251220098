<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LinkGoogleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'current_password:web']];
    }

    public function messages(): array
    {
        return ['current_password.required' => 'Vui lòng xác nhận mật khẩu hiện tại.', 'current_password.current_password' => 'Mật khẩu hiện tại không đúng.'];
    }
}
