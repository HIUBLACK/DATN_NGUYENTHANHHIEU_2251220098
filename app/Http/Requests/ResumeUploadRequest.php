<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResumeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['pdf' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:5120']];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Vui lòng chọn một CV PDF.',
            'pdf.file' => 'Không đọc được tệp tải lên.',
            'pdf.mimes' => 'Chỉ chấp nhận tệp PDF.',
            'pdf.extensions' => 'Tệp phải có phần mở rộng .pdf.',
            'pdf.max' => 'CV PDF không được vượt quá 5 MB.',
            'pdf.uploaded' => 'Tải tệp thất bại. Kiểm tra giới hạn tải lên của PHP.',
        ];
    }
}
