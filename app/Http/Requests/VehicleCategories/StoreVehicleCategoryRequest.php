<?php

namespace App\Http\Requests\VehicleCategories;

use App\Http\Requests\Concerns\ValidatesEmbeddedDefaults;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleCategoryRequest extends FormRequest
{
    use ValidatesEmbeddedDefaults;

    public function authorize(): bool
    {
        return $this->user()->can('create', VehicleCategory::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name_en' => ['required', 'string', 'max:255', Rule::unique('vehicle_categories', 'name_en')->where('tenant_id', $tenantId)],
            'name_ar' => ['required', 'string', 'max:255', Rule::unique('vehicle_categories', 'name_ar')->where('tenant_id', $tenantId)],

            ...$this->embeddedDefaultRules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'name_en' => __('Name (English)'),
            'name_ar' => __('Name (Arabic)'),
            ...$this->embeddedDefaultAttributes(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmbeddedDefaults();
    }
}
