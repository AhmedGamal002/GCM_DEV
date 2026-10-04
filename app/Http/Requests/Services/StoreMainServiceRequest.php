<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\MainService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.9.1 "Create new main service" form.
 */
class StoreMainServiceRequest extends FormRequest
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
        return $this->user()->can('create', MainService::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('main_services', 'name')->where('tenant_id', $tenantId)],
            'service_type' => ['required', Rule::in(MainService::TYPES)],
            'vehicle_category_ids' => ['required', 'array', 'min:1'],
            'vehicle_category_ids.*' => ['integer', 'distinct', Rule::exists('vehicle_categories', 'id')->where('tenant_id', $tenantId)],
            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
