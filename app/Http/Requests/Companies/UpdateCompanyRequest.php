<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use App\Models\User;
use Closure;

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

        // FRD §1.11: "حساب ممثل العميل" — optional; one of THIS company's
        // project managers (client accounts of another company, auditors and
        // GCM staff are refused). An unchanged value is always accepted so
        // saving other fields still works after the rep was later put on
        // vacation / deactivated.
        $rules['representative_id'] = ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
            $company = $this->targetCompany();

            if ((int) $value === $company->representative_id) {
                return;
            }

            $user = User::find($value);

            if (! $user || $user->company_id !== $company->id || ! $user->hasRole('client_project_manager') || $user->status !== 'active') {
                $fail(__('Choose an active project manager of this company.'));
            }
        }];

        return $rules;
    }
}
