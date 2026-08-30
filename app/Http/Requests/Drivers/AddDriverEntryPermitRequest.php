<?php

namespace App\Http\Requests\Drivers;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;

class AddDriverEntryPermitRequest extends FormRequest
{
    private ?Driver $targetDriver = null;

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
            'area_name' => ['required', 'string', 'max:255'],
            'permit_number' => ['required', 'string', 'max:64'],
            'valid_to' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
