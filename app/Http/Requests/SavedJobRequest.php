<?php

namespace App\Http\Requests;

use App\Models\SavedJob;
use Illuminate\Foundation\Http\FormRequest;

class SavedJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SavedJob::class) ?? false;
    }

    public function rules(): array
    {
        return $this->isMethod('delete') ? [] : ['saved' => ['required', 'boolean']];
    }
}
