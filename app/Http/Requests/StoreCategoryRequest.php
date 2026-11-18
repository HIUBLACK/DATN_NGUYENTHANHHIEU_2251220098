<?php

namespace App\Http\Requests;

use App\Support\AdminCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-categories');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => AdminCategory::cleanName($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/[^\s\p{Z}\p{C}]/u'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['required', 'boolean'],
            'job_category_id' => $this->route('categoryType') === 'specialties'
                ? ['required', 'integer', 'min:1'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'name.regex' => 'Tên phải có nội dung, không chỉ gồm khoảng trắng hoặc ký tự ẩn.',
            'max.string' => ':attribute không được vượt quá :max ký tự.',
            'integer' => ':attribute phải là số nguyên.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'min' => ':attribute phải có giá trị tối thiểu :min.',
            'boolean' => 'Trạng thái không hợp lệ.',
            'between' => ':attribute phải nằm trong khoảng :min đến :max.',
            'prohibited' => 'Không được gửi :attribute cho loại danh mục này.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Tên', 'description' => 'Mô tả', 'sort_order' => 'Thứ tự', 'job_category_id' => 'Nhóm nghề', 'is_active' => 'Trạng thái', 'updated_at' => 'Phiên bản dữ liệu'];
    }
}
