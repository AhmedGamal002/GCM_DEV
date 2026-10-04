<?php

namespace App\Http\Requests\Companies;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.11.2 "Create new client company" form.
 *
 * `prefix` ("الاسم المختصر") is 3 English letters, unique among the
 * tenant's companies — normalised to upper case before validating so
 * "abc" and "ABC" can't coexist.
 *
 * The optional "client representative account" (FRD §1.11) is only offered
 * on edit (UpdateCompanyRequest): it picks one of the company's own project
 * managers, and a company that is just being created has none yet — the FRD
 * itself says it "can be updated later, after the project managers' accounts
 * are created".
 */
class StoreCompanyRequest extends FormRequest
{
    use NormalizesRichTextInput;

    protected function prepareForValidation(): void
    {
        if ($this->has('prefix')) {
            $this->merge(['prefix' => strtoupper(trim((string) $this->input('prefix')))]);
        }

        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Company::class);
    }

    public function rules(): array
    {
        return $this->baseRules(Rule::unique('companies', 'prefix')->where('tenant_id', app('tenant')->id)) + [
            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }

    /**
     * Shared with UpdateCompanyRequest so create and edit can't drift.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function baseRules(mixed $prefixUnique): array
    {
        $file = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'];
        $digits = ['nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['required', 'string', 'regex:/^[A-Z]{3}$/', $prefixUnique],
            'business_sector' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],

            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'location_url' => ['nullable', 'url:http,https', 'max:2048'],

            'contract_number' => $digits,
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'contract_attachment' => $file,

            'cr_number' => $digits,
            'cr_attachment' => $file,

            'tax_number' => $digits,
            'tax_attachment' => $file,

            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.regex' => __('The short name must be exactly 3 English letters.'),
            'prefix.unique' => __('This short name is already used by another company.'),
            'contract_number.regex' => __('The number must contain digits only.'),
            'cr_number.regex' => __('The number must contain digits only.'),
            'tax_number.regex' => __('The number must contain digits only.'),
        ];
    }
}
