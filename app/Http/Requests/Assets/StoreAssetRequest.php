<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Http\Requests\Concerns\ValidatesAffiliation;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD §1.7.3 "Create new asset" form. The affiliation radio is GCM or a
 * contractor; choosing a contractor requires picking one (see
 * ValidatesAffiliation). It is locked after creation — only the name is
 * editable (FRD §1.7.4).
 */
class StoreAssetRequest extends FormRequest
{
    use NormalizesRichTextInput, ValidatesAffiliation;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Asset::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'asset_type' => ['required', Rule::in(['container', 'tank'])],

            'asset_capacity_category_id' => [
                'required', 'integer',
                Rule::exists('asset_capacity_categories', 'id')->where('tenant_id', $tenantId),
            ],

            'compatible_vehicle_category_ids' => ['required', 'array', 'min:1'],
            'compatible_vehicle_category_ids.*' => ['integer', Rule::exists('vehicle_categories', 'id')->where('tenant_id', $tenantId)],

            'operational_status' => ['required', Rule::in(['active', 'on_maintenance', 'deactivated'])],

            ...$this->affiliationRules(),

            'purchase_date' => ['nullable', 'date'],
            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // The capacity list on the form only shows capacities that fit
            // the chosen asset type — enforce the same server-side.
            $categoryId = $this->input('asset_capacity_category_id');
            $assetType = $this->input('asset_type');

            if ($categoryId && $assetType) {
                $category = AssetCapacityCategory::find($categoryId);
                if ($category && ! $category->fitsType($assetType)) {
                    $validator->errors()->add(
                        'asset_capacity_category_id',
                        __('The selected capacity does not apply to this asset type.')
                    );
                }
            }
        });
    }
}
