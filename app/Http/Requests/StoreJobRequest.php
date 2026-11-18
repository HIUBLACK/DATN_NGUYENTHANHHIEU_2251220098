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
        return JobInput::rules($this->all()) + ['creation_key' => ['required', 'uuid']];
    }
}
