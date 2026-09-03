<?php

namespace App\Http\Requests\AssetCapacityCategories;

use App\Models\AssetCapacityCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD §1.7.2 "Create capacity category" form. Capacity carries BOTH the
 * CBM and TON equivalents — both required, not a choice of one unit.
 */
class StoreAssetCapacityCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AssetCapacityCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['container', 'tank', 'both'])],
            'capacity_cbm' => ['required', 'numeric', 'gt:0'],
            'capacity_ton' => ['required', 'numeric', 'gt:0'],
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
