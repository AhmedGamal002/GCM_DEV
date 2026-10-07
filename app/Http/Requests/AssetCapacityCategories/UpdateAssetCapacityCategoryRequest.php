<?php

namespace App\Http\Requests\AssetCapacityCategories;

use App\Models\AssetCapacityCategory;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD §1.7.2 edit page: only the name is editable per the FRD; the client
 * also asked for the additional data (plain notes) to stay editable.
 * `applies_to` and the capacities are permanently locked after creation.
 * Anything else in the payload is ignored (not in the validated set).
 */
class UpdateAssetCapacityCategoryRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?AssetCapacityCategory $targetCategory = null;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

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
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
