<?php

namespace App\Http\Requests\Drivers;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * FRD's "المركبة الافتراضية – Default Car" section (residence/license/
 * etc. below it are unrelated, pre-existing) — both fields required:
 * `vehicle_category_ids` (which of the 5 fixed categories the driver is
 * qualified to drive) and `default_vehicle_id` (must be one of the
 * tenant's vehicles, and — see withValidator() — must actually belong to
 * one of the selected categories, matching the FRD's "قائمة تشمل ارقام
 * المركبات المرتبطة بالتصنيف" wording).
 */
class StoreDriverRequest extends FormRequest
{
    use NormalizesRichTextInput;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Driver::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,on_vacation,deactivated'],
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

            'entry_permits' => ['nullable', 'array'],
            'entry_permits.*.area_name' => ['required', 'string', 'max:255'],
            'entry_permits.*.permit_number' => ['required', 'string', 'max:64'],
            'entry_permits.*.valid_to' => ['required', 'date'],
            'entry_permits.*.attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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

            // A vehicle can be "the default" for at most one driver at a
            // time — otherwise two drivers both showing the same truck as
            // theirs is meaningless/confusing. Tenant-scoped automatically
            // (Driver uses BelongsToTenant), so this can't false-positive
            // against another tenant's driver using the same vehicle id.
            if ($vehicleId && Driver::where('default_vehicle_id', $vehicleId)->exists()) {
                $validator->errors()->add(
                    'default_vehicle_id',
                    __('This vehicle is already set as another driver\'s default vehicle.')
                );
            }
        });
    }
}
