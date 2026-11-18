<?php

namespace App\Http\Requests;

class CategoryStatusRequest extends StoreCategoryRequest
{
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean'], 'updated_at' => ['required', 'string', 'max:30']];
    }
}
