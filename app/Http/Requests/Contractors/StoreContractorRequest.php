<?php

namespace App\Http\Requests\Contractors;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.10.2 "Create new contractor" form.
 *
 * `prefix` ("الاسم المختصر") is 3 English letters, unique among the tenant's
 * contractors — normalised to upper case before validating so "abc" and
 * "ABC" can't coexist. It is not compared with client companies' short names:
 * the contractor's ID carries a "C" (ALN-C0001), so the two can never clash.
 *
 * The optional "contractor representative account" (FRD §1.10.2) isn't here:
 * it picks one of the contractor's own user accounts, which don't exist until
 * the contractor user role is built.
 *
 * `main_service_ids` = the "Certified services" checkboxes; none ticked
 * means the contractor supports every service.
 */
class StoreContractorRequest extends FormRequest
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
        return $this->user()->can('create', Contractor::class);
    }

    public function rules(): array
    {
        return $this->baseRules(Rule::unique('contractors', 'prefix')->where('tenant_id', app('tenant')->id)) + [
            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }

    /**
     * Shared with UpdateContractorRequest so create and edit can't drift.
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
            'trading_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],

            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'location_url' => ['nullable', 'url:http,https', 'max:2048'],

            'main_service_ids' => ['nullable', 'array'],
            'main_service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('main_services', 'id')->where('tenant_id', app('tenant')->id),
            ],

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
            'prefix.unique' => __('This short name is already used by another contractor.'),
            'contract_number.regex' => __('The number must contain digits only.'),
            'cr_number.regex' => __('The number must contain digits only.'),
            'tax_number.regex' => __('The number must contain digits only.'),
        ];
    }
}
