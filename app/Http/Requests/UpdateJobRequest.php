<?php

namespace App\Http\Requests;

use App\Models\Job;
use App\Support\JobInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Job::findOrFail($this->route('job'))) ?? false;
    }

    public function rules(): array
    {
        return array_replace(JobInput::rules($this->all()), ['position' => ['exclude'], 'intent' => ['sometimes', 'in:draft,submit']]) + [
            'version_id' => ['required', 'integer'],
            'updated_at' => ['required', 'date_format:Y-m-d H:i:s.u'],
        ];
    }
}
