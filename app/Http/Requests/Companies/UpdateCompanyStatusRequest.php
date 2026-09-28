<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyStatusRequest extends FormRequest
{
    private ?Company $targetCompany = null;

    private function targetCompany(): Company
    {
        return $this->targetCompany ??= Company::findOrFail($this->route('company'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetCompany());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
