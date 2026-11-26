<?php

namespace App\Http\Requests;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('moderate', Job::findOrFail($this->route('job')));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:blocked,restore'],
            'expected_status' => ['required', 'in:draft,open,paused,closed,blocked'],
            'expected_log_id' => ['required', 'integer', 'min:0'],
            'updated_at' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/[^\s\p{Z}\p{C}]/u'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'lý do', 'status' => 'thao tác', 'updated_at' => 'phiên bản dữ liệu'];
    }
}
