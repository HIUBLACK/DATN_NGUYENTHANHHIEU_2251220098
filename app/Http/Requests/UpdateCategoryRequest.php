<?php

namespace App\Http\Requests;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'updated_at' => ['required', 'string', 'max:30']];
    }
}
