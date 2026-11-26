<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class RecruiterApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Recruiter && $this->user()->status === UserStatus::Active;
    }

    public function rules(): array
    {
        if ($this->routeIs('recruiter.application-note')) {
            return [
                'content' => ['required', 'string', 'max:2000', 'regex:/[^\s\p{Z}]/u'],
                'note_id' => ['nullable', 'integer', 'min:1'],
                'note_updated_at' => ['required_with:note_id', 'string', 'max:30'],
            ];
        }

        return [
            'status' => ['required', 'in:reviewing,interview,hired,rejected'],
            'expected_status' => ['required', 'in:applied,reviewing,interview'],
            'updated_at' => ['required', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return ['content' => 'nội dung ghi chú', 'status' => 'trạng thái', 'expected_status' => 'trạng thái cũ'];
    }
}
