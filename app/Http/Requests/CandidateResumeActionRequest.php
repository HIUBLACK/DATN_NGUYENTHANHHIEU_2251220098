<?php

namespace App\Http\Requests;

use App\Models\Resume;
use Illuminate\Foundation\Http\FormRequest;

class CandidateResumeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Resume::class) ?? false;
    }

    public function rules(): array
    {
        return match ($this->route('action')) {
            'rename' => ['name' => ['required', 'string', 'max:100', 'regex:/[^\s\p{Z}]/u']],
            'delete' => ['replacement' => ['nullable', 'uuid']],
            default => [],
        };
    }
}
