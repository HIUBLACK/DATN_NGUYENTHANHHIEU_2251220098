<?php

namespace App\Http\Requests;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;

class SubmitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Job::findOrFail($this->route('job'))) ?? false;
    }

    public function rules(): array
    {
        return [
            'version_id' => ['required', 'integer'],
            'updated_at' => ['required', 'date_format:Y-m-d H:i:s.u'],
        ];
    }
}
