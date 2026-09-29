<?php

namespace App\Http\Requests\Facilities;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\IntermediateFacility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.8 edit page: name + optional data. The prefix is never
 * accepted (locked). The environmental service / recycling efficiency are
 * accepted here and refused by UpdateFacilityAction when the facility is
 * already in use. operational_status changes only through the status
 * endpoint.
 *
 * Not route-model-bound (`{facility}` is a raw id, see FacilityController).
 */
class UpdateFacilityRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?IntermediateFacility $targetFacility = null;

    private function targetFacility(): IntermediateFacility
    {
        return $this->targetFacility ??= IntermediateFacility::findOrFail($this->route('facility'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetFacility());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],

            'environmental_service' => ['sometimes', Rule::in(IntermediateFacility::SERVICES)],
            'recycling_efficiency' => ['nullable', 'numeric', 'between:0,100'],

            'address' => ['nullable', 'string', 'max:255'],
            'location_url' => ['nullable', 'url', 'max:2048'],

            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'contract_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],

            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $facility = $this->targetFacility();

            // A field the locked form doesn't send keeps its stored value.
            $service = $this->input('environmental_service', $facility->environmental_service);
            $efficiency = $this->has('recycling_efficiency')
                ? $this->input('recycling_efficiency')
                : $facility->recycling_efficiency;

            if ($service === 'recycle' && ($efficiency === null || $efficiency === '')) {
                $validator->errors()->add('recycling_efficiency', __('The recycling efficiency is required for a recycling facility.'));
            }

            if ($service !== 'recycle' && $this->filled('recycling_efficiency')) {
                $validator->errors()->add('recycling_efficiency', __('The recycling efficiency applies to recycling facilities only.'));
            }
        });
    }
}
