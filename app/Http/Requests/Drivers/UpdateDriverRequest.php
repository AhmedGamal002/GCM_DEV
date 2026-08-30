<?php

namespace App\Http\Requests\Drivers;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    private ?Driver $targetDriver = null;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'additional_data' => ['nullable', 'string'],

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
}
