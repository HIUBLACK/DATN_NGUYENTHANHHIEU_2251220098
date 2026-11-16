<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'regex:/^[+0-9 ()-]{9,20}$/'],
            'location' => ['nullable', 'string', 'max:100'],
            'profession' => ['nullable', 'string', 'max:100'],
            'experience' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng điền :attribute.',
            'email.email' => 'Địa chỉ email chưa hợp lệ.',
            'phone.regex' => 'Số điện thoại chưa hợp lệ.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'avatar.image' => 'Vui lòng chọn ảnh hợp lệ.',
            'avatar.mimes' => 'Chỉ nhận ảnh JPG, PNG hoặc WebP.',
        ];
    }
}
