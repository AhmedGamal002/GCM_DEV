<?php

namespace App\Http\Requests\Vehicles;

use App\Domain\Vehicles\Rules\EmbeddedCapacityFitsVehicleRule;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD §1.5: on edit "all data is editable" — except operational_status,
 * which changes only through the status endpoint. Not route-model-bound
 * (the `{vehicle}` param is a raw id, see VehicleController's docblock).
 */
class UpdateVehicleRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?Vehicle $targetVehicle = null;

    private function targetVehicle(): Vehicle
    {
        return $this->targetVehicle ??= Vehicle::findOrFail($this->route('vehicle'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetVehicle());
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        $rules = [
            'plate_letters' => ['required', 'string', 'max:16'],
            'plate_numbers' => [
                'required', 'string', 'max:16', 'regex:/^[0-9]+$/',
                Rule::unique('vehicles')
                    ->ignore($this->targetVehicle()->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                        ->where('plate_letters', $this->input('plate_letters'))),
            ],
            'vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],

            'has_embedded_container' => ['required', 'boolean'],
            'embedded_container_type' => ['nullable', 'required_if:has_embedded_container,1,true', Rule::in(['container', 'tank'])],
            'embedded_asset_capacity_category_id' => [
                'nullable', 'required_if:has_embedded_container,1,true',
                Rule::exists('asset_capacity_categories', 'id')->where('tenant_id', $tenantId),
                new EmbeddedCapacityFitsVehicleRule(
                    $this->boolean('has_embedded_container') ? (int) $this->input('vehicle_category_id') : null,
                    $this->boolean('has_embedded_container') ? $this->input('embedded_container_type') : null,
                ),
            ],

            'photo_front' => ['nullable', 'image', 'max:4096'],
            'photo_back' => ['nullable', 'image', 'max:4096'],
            'additional_data' => ['nullable', 'string'],

            'documents' => ['required', 'array'],

            'entry_permits' => ['nullable', 'array'],
            'entry_permits.*.id' => ['nullable', 'integer'],
            'entry_permits.*.area_name' => ['required', 'string', 'max:255'],
            'entry_permits.*.permit_number' => ['required', 'string', 'max:255', 'regex:/^[0-9]+$/'],
            'entry_permits.*.valid_to' => ['required', 'date'],
            'entry_permits.*.attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];

        foreach (VehicleDocument::SINGLE_TYPES as $type) {
            $rules["documents.{$type}"] = ['required', 'array'];
            $rules["documents.{$type}.number"] = ['required', 'string', 'max:255', 'regex:/^[0-9]+$/'];
            $rules["documents.{$type}.valid_to"] = ['required', 'date'];
            $rules["documents.{$type}.attachment"] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'plate_numbers.regex' => __('The plate number must contain digits only.'),
            'entry_permits.*.permit_number.regex' => __('The permit number must contain digits only.'),
        ];

        foreach (VehicleDocument::SINGLE_TYPES as $type) {
            $messages["documents.{$type}.number.regex"] = __('The document number must contain digits only.');
        }

        return $messages;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('has_embedded_container')) {
            $this->merge([
                'has_embedded_container' => filter_var($this->input('has_embedded_container'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Plate letters are stored (and uniqueness-checked) upper-case.
        if ($this->filled('plate_letters')) {
            $this->merge(['plate_letters' => mb_strtoupper(trim($this->input('plate_letters')))]);
        }

        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }
}
