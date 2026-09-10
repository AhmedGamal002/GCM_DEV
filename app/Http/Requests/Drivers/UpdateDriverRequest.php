<?php

namespace App\Http\Requests\Drivers;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?Driver $targetDriver = null;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    /**
     * Not route-model-bound — same reason as every other {user}/{driver}
     * route param in this app (SubstituteBindings runs before the
     * `tenant` middleware binds app('tenant')). Resolved here through the
     * normal tenant-scoped query, same as the controller.
     */
    private function targetDriver(): Driver
    {
        return $this->targetDriver ??= Driver::findOrFail($this->route('driver'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetDriver());
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'additional_data' => ['nullable', 'string'],

            'vehicle_category_ids' => ['required', 'array', 'min:1'],
            'vehicle_category_ids.*' => ['integer', 'exists:vehicle_categories,id'],
            'default_vehicle_id' => [
                'required', 'integer',
                Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId),
            ],

            'residence_number' => ['required', 'string', 'max:64'],
            'residence_valid_to' => ['required', 'date'],
            'residence_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'license_number' => ['required', 'string', 'max:64'],
            'license_valid_to' => ['required', 'date'],
            'license_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'operational_license_number' => ['required', 'string', 'max:64'],
            'operational_license_valid_to' => ['required', 'date'],
            'operational_license_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'insurance_number' => ['required', 'string', 'max:64'],
            'insurance_valid_to' => ['required', 'date'],
            'insurance_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $vehicleId = $this->input('default_vehicle_id');
            $categoryIds = $this->input('vehicle_category_ids', []);

            if (! $vehicleId || empty($categoryIds)) {
                return;
            }

            $vehicle = Vehicle::find($vehicleId);

            // $categoryIds comes straight from form input — always
            // strings — so this must compare as ints, not loosely (in
            // case a non-numeric slips through) and not strictly (which
            // would spuriously fail on "3" !== 3 every time).
            if ($vehicle && ! in_array((int) $vehicle->vehicle_category_id, array_map('intval', $categoryIds), true)) {
                $validator->errors()->add(
                    'default_vehicle_id',
                    __('The default vehicle must belong to one of the selected vehicle categories.')
                );
            }

            // Same "one default driver per vehicle" rule as
            // StoreDriverRequest — excluding this driver's own row so
            // re-submitting the edit form with their existing default
            // vehicle unchanged doesn't spuriously reject against itself.
            if ($vehicleId && Driver::where('default_vehicle_id', $vehicleId)
                ->where('id', '!=', $this->targetDriver()->id)
                ->exists()) {
                $validator->errors()->add(
                    'default_vehicle_id',
                    __('This vehicle is already set as another driver\'s default vehicle.')
                );
            }
        });
    }
}
