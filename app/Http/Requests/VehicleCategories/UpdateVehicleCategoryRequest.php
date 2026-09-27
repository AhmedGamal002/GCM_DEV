<?php

namespace App\Http\Requests\VehicleCategories;

use App\Models\VehicleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleCategoryRequest extends FormRequest
{
    private ?VehicleCategory $target = null;

    /**
     * Not route-model-bound — the `{category}` param is a raw id (same
     * reason as every other route here: SubstituteBindings runs before the
     * `tenant` middleware binds app('tenant')), resolved through the
     * normal tenant-scoped query.
     */
    private function target(): VehicleCategory
    {
        return $this->target ??= VehicleCategory::findOrFail($this->route('category'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;
        $id = $this->target()->id;

        return [
            'name_en' => ['required', 'string', 'max:255', Rule::unique('vehicle_categories', 'name_en')->where('tenant_id', $tenantId)->ignore($id)],
            'name_ar' => ['required', 'string', 'max:255', Rule::unique('vehicle_categories', 'name_ar')->where('tenant_id', $tenantId)->ignore($id)],
        ];
    }

    public function attributes(): array
    {
        return ['name_en' => __('Name (English)'), 'name_ar' => __('Name (Arabic)')];
    }
}
