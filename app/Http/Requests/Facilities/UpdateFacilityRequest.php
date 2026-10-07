<?php

namespace App\Http\Requests\Facilities;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\IntermediateFacility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD V01.14 §1.8 edit page: "اسم المنشأة والبيانات الاختيارية" — the name
 * and the optional data (logo, address, map link, contract data and
 * attachment, additional data), nothing else. The prefix, the environmental
 * service and the recycling efficiency are fixed at creation: they are not
 * in the validated set, so a payload carrying them is simply ignored.
 * operational_status changes only through the status endpoint.
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

            'address' => ['nullable', 'string', 'max:255'],
            'location_url' => ['nullable', 'url', 'max:2048'],

            'contract_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'contract_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],

            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'contract_number.regex' => __('The number must contain digits only.'),
        ];
    }
}
