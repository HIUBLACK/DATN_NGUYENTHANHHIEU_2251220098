<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-system');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,locked'],
            'expected_status' => ['required', 'in:active,locked'],
            'expected_log_id' => ['required', 'integer', 'min:0'],
            'updated_at' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'lý do', 'status' => 'trạng thái', 'updated_at' => 'phiên bản dữ liệu', 'expected_status' => 'trạng thái hiện tại'];
    }
}
