<?php

namespace App\Http\Requests;

use App\Models\CandidateProfile;
use Illuminate\Foundation\Http\FormRequest;

class CandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CandidateProfile::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/[^\s\p{Z}]/u'],
            'email' => ['prohibited'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?(?=(?:\D*\d){8,15}\D*$)[0-9 ()-]+$/'],
            'location' => ['nullable', 'string', 'max:100'],
            'profession' => ['nullable', 'string', 'max:100'],
            'experience' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000', 'prohibited_if:remove_avatar,1'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'candidate_id' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'password' => ['prohibited'],
            'google_subject' => ['prohibited'],
            'has_local_password' => ['prohibited'],
            'avatar_path' => ['prohibited'],
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
            'avatar.dimensions' => 'Ảnh tối đa 5000 × 5000 pixel.',
            'avatar.prohibited_if' => 'Chỉ chọn tải ảnh mới hoặc xóa ảnh hiện tại trong một lần lưu.',
            'prohibited' => 'Không được thay đổi :attribute qua form hồ sơ.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'họ tên', 'phone' => 'số điện thoại', 'location' => 'địa chỉ', 'profession' => 'vị trí chuyên môn', 'experience' => 'kinh nghiệm', 'bio' => 'giới thiệu bản thân', 'avatar' => 'ảnh đại diện'];
    }
}
