<?php

namespace App\Http\Requests\AssetCapacityCategories;

use App\Models\AssetCapacityCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD §1.7.2 edit page: the name is the ONLY editable field. `applies_to`
 * and the capacities are permanently locked after creation. Anything
 * else in the payload is ignored (not in the validated set).
 */
class UpdateAssetCapacityCategoryRequest extends FormRequest
{
    private ?AssetCapacityCategory $targetCategory = null;

    private function targetCategory(): AssetCapacityCategory
    {
        return $this->targetCategory ??= AssetCapacityCategory::findOrFail($this->route('category'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetCategory());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
