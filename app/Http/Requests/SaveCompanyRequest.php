<?php

namespace App\Http\Requests;

use App\Support\CompanyOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SaveCompanyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('country') !== 'Việt Nam') {
            $this->merge(['province_code' => null, 'ward_code' => null]);
        }
        if ($this->boolean('no_website')) {
            $this->merge(['website' => null]);
        }
    }

    public function rules(): array
    {
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'dimensions:max_width=5000,max_height=5000'];
        $company = $this->user()?->company;
        $types = array_filter([...CompanyOptions::TYPES, $company?->company_type]);
        $countries = array_filter([...array_values(CompanyOptions::countries()), $company?->country]);

        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['exclude'],
            'summary' => ['exclude'],
            'description' => ['nullable', 'string', 'max:10000'],
            'work_environment' => ['nullable', 'string', 'max:3000'],
            'founded_year' => ['exclude'],
            'size' => ['nullable', Rule::in(['1–10', '11–50', '51–200', '201–500', '501–1000', 'Trên 1000'])],
            'industry_id' => ['nullable', 'integer', Rule::in(array_keys(config('job-catalog.industries')))],
            'company_type' => ['nullable', Rule::in($types)],
            'country' => ['nullable', Rule::in($countries)],
            'no_website' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?=(?:\D*\d){8,15}\D*$)[0-9+() .-]{8,30}$/'],
            'address' => ['required', 'string', 'max:255'],
            'province_code' => ['nullable', 'required_if:country,Việt Nam', 'string', Rule::in(array_keys(\App\Support\Provinces::ALL))],
            'ward_code' => ['nullable', 'required_if:country,Việt Nam', 'string', 'size:5', \App\Support\VietnamAddress::wardRule($this->input('province_code'))],
            'working_hours' => ['exclude'],
            'working_from' => ['nullable', 'required_with:working_to', 'integer', 'between:1,7'],
            'working_to' => ['nullable', 'required_with:working_from', 'integer', 'between:1,7'],
            'ot_policy' => ['nullable', Rule::in(CompanyOptions::OVERTIME)],
            'benefits' => ['exclude'],
            'technologies' => ['exclude'],
            'logo' => ['nullable', 'file', 'max:2048', ...$image],
            'cover' => ['exclude'],
            'office_images' => ['nullable', 'array', 'max:6'],
            'office_images.*' => ['required', 'file', 'max:3072', ...$image],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_cover' => ['exclude'],
            'remove_office_images' => ['sometimes', 'array', 'max:6'],
            'remove_office_images.*' => ['integer', 'min:0', 'max:5', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'required_if' => 'Vui lòng chọn :attribute.',
            'required_with' => 'Vui lòng chọn đủ ngày bắt đầu và ngày kết thúc.',
            'in' => 'Lựa chọn :attribute không hợp lệ.',
            'between' => 'Ngày làm việc phải nằm trong tuần.',
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
        return ['name' => 'tên doanh nghiệp', 'email' => 'email', 'phone' => 'số điện thoại', 'address' => 'địa chỉ', 'logo' => 'logo', 'office_images' => 'ảnh văn phòng', 'work_environment' => 'giới thiệu môi trường làm việc', 'province_code' => 'tỉnh/thành phố', 'ward_code' => 'xã/phường/đặc khu'];
    }
}
