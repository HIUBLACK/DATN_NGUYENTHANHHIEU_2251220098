<?php

namespace App\Http\Requests;

use App\Models\Job;
use App\Support\JobInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Job::class) ?? false;
    }

    public function rules(): array
    {
        return array_replace(JobInput::rules($this->all()), ['position' => ['exclude'], 'intent' => ['sometimes', 'in:draft,submit']]) + ['creation_key' => ['required', 'uuid']];
    }
}
