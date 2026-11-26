<?php

namespace App\Http\Requests;

use App\Models\Company;

class UpdateCompanyRequest extends SaveCompanyRequest
{
    public function authorize(): bool
    {
        $company = Company::where('public_id', $this->route('company'))->firstOrFail();

        return $this->user()?->can('update', $company) ?? false;
    }

    public function rules(): array
    {
        return parent::rules() + ['updated_at' => ['required', 'string', 'date_format:Y-m-d H:i:s.u']];
    }
}
