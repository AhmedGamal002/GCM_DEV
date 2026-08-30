<?php

namespace App\Http\Requests\Drivers;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Driver::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,on_vacation,deactivated'],
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

            'entry_permits' => ['nullable', 'array'],
            'entry_permits.*.area_name' => ['required', 'string', 'max:255'],
            'entry_permits.*.permit_number' => ['required', 'string', 'max:64'],
            'entry_permits.*.valid_to' => ['required', 'date'],
            'entry_permits.*.attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
