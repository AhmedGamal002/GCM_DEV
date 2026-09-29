<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;

/**
 * FRD V01.14 §1.11.4: every company field is editable by (System Admin /
 * Data Entry) EXCEPT the short name — it is "mixed with numbers to express
 * a unique number belonging to the company" (the company ID is
 * prefix + number, and later project/PO/trip numbers are built from it),
 * so it is locked after creation. A `prefix` in the payload is simply not
 * validated or saved. Deactivation has its own endpoint
 * (UpdateCompanyStatusRequest).
 *
 * Not route-model-bound (the `{company}` param is a raw id — see
 * CompanyController's docblock).
 */
class UpdateCompanyRequest extends StoreCompanyRequest
{
    private ?Company $targetCompany = null;

    private function targetCompany(): Company
    {
        return $this->targetCompany ??= Company::findOrFail($this->route('company'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetCompany());
    }

    public function rules(): array
    {
        $rules = $this->baseRules(null);
        unset($rules['prefix']);

        return $rules;
    }
}
