<?php

namespace App\Http\Requests\Facilities;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\IntermediateFacility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.8 "Create new facility" form.
 */
class StoreFacilityRequest extends FormRequest
{
    use NormalizesRichTextInput;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }

        // The prefix is a 3-letter code; store it upper-case so "abc" and
        // "ABC" can't both exist.
        if (is_string($this->input('prefix'))) {
            $this->merge(['prefix' => strtoupper(trim($this->input('prefix')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', IntermediateFacility::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'prefix' => [
                'required', 'string', 'regex:/^[A-Z]{3}$/',
                Rule::unique('intermediate_facilities', 'prefix')->where('tenant_id', $tenantId),
            ],
            'logo' => ['nullable', 'image', 'max:2048'],

            'environmental_service' => ['required', Rule::in(IntermediateFacility::SERVICES)],
            // Shown — and required — for recycling facilities only.
            'recycling_efficiency' => [
                'required_if:environmental_service,recycle',
                'prohibited_unless:environmental_service,recycle',
                'nullable', 'numeric', 'between:0,100',
            ],

            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],

            'address' => ['nullable', 'string', 'max:255'],
            'location_url' => ['nullable', 'url', 'max:2048'],

            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'contract_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],

            'additional_data' => ['nullable', 'string'],
        ];
    }
}
