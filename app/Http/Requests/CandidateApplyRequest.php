<?php

namespace App\Http\Requests;

use App\Models\Resume;
use Illuminate\Foundation\Http\FormRequest;

class CandidateApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Resume::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'resume' => ['required', 'uuid'],
            'letter' => ['nullable', 'string', 'max:2000'],
            'confirmation' => ['accepted'],
            'job_version_id' => ['required', 'integer', 'min:1'],
            'attempt_no' => ['required', 'integer', 'in:1,2'],
        ];
    }

    public function messages(): array
    {
        return ['resume.required' => 'Hãy chọn CV để ứng tuyển.', 'confirmation.accepted' => 'Bạn cần xác nhận nộp hồ sơ.'];
    }
}
