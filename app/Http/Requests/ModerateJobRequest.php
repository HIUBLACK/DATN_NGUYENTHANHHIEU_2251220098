<?php

namespace App\Http\Requests;

use App\Models\JobVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ModerateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('moderate', JobVersion::findOrFail($this->route('version'))->job);
    }

    public function rules(): array
    {
        return [
            'updated_at' => ['required', 'string', 'max:30'],
            'job_updated_at' => ['required', 'string', 'max:30'],
            'reason' => $this->route('action') === 'reject'
                ? ['required', 'string', 'max:2000', 'regex:/[^\s\p{Z}\p{C}]/u'] : ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['required' => ':attribute là bắt buộc.', 'reason.regex' => 'Lý do từ chối phải có nội dung.', 'max.string' => ':attribute không quá :max ký tự.'];
    }

    public function attributes(): array
    {
        return ['reason' => 'Lý do từ chối', 'updated_at' => 'Phiên bản dữ liệu', 'job_updated_at' => 'Phiên bản tin'];
    }
}
