<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SaveCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'dimensions:max_width=5000,max_height=5000'];

        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:'.now('Asia/Ho_Chi_Minh')->year],
            'size' => ['nullable', Rule::in(['1–10', '11–50', '51–200', '201–500', '501–1000', 'Trên 1000'])],
            'industry_id' => ['nullable', 'integer', Rule::exists('industries', 'id')],
            'company_type' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?=(?:\D*\d){8,15}\D*$)[0-9+() .-]{8,30}$/'],
            'address' => ['required', 'string', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'ot_policy' => ['nullable', 'string', 'max:2000'],
            'benefits' => ['nullable', 'string', 'max:3000'],
            'technologies' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'max:2048', ...$image],
            'cover' => ['nullable', 'file', 'max:5120', ...$image],
            'office_images' => ['nullable', 'array', 'max:6'],
            'office_images.*' => ['required', 'file', 'max:3072', ...$image],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_cover' => ['sometimes', 'boolean'],
            'remove_office_images' => ['sometimes', 'array', 'max:6'],
            'remove_office_images.*' => ['integer', 'min:0', 'max:5', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'image' => 'Chỉ chấp nhận ảnh hợp lệ.',
            'mimes' => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'extensions' => 'Đuôi tệp phải là jpg, jpeg, png hoặc webp.',
            'dimensions' => 'Kích thước ảnh tối đa 5000 × 5000 pixel.',
            'website.url' => 'Website phải bắt đầu bằng http:// hoặc https://.',
            'email.email' => 'Email không hợp lệ.',
            'phone.regex' => 'Số điện thoại phải có 8–15 chữ số, tối đa 30 ký tự gồm dấu phân cách.',
            'industry_id.exists' => 'Lĩnh vực không tồn tại. Vui lòng chọn lại.',
            'updated_at.date_format' => 'Phiên bản hồ sơ không hợp lệ. Vui lòng tải lại trang.',
            'founded_year.max' => 'Năm thành lập không được lớn hơn năm hiện tại.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên doanh nghiệp', 'email' => 'email', 'phone' => 'số điện thoại', 'address' => 'địa chỉ', 'logo' => 'logo', 'cover' => 'ảnh bìa', 'office_images' => 'ảnh văn phòng'];
    }
}
