<?php

namespace App\Http\Requests;

use App\Models\Company;

class StoreCompanyRequest extends SaveCompanyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Company::class) ?? false;
    }
}
