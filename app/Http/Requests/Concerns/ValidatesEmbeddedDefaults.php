<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Vehicles\Rules\EmbeddedCapacityFitsTypeRule;
use Illuminate\Validation\Rule;

/**
 * The embedded-container defaults on a vehicle category (client request):
 * the values the create-vehicle form pre-fills once the category is picked.
 * Same shape as the vehicle's own fields, so the same rules apply — a
 * default "has one" needs a kind, and a capacity must fit that kind (see
 * EmbeddedCapacityFitsTypeRule). The capacity stays optional here: a
 * category may only know "these are always tanks" without a fixed size.
 */
trait ValidatesEmbeddedDefaults
{
    protected function embeddedDefaultRules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'default_has_embedded_container' => ['sometimes', 'boolean'],
            'default_embedded_container_type' => ['nullable', 'required_if:default_has_embedded_container,1,true', Rule::in(['container', 'tank'])],
            'default_embedded_asset_capacity_category_id' => [
                'nullable',
                Rule::exists('asset_capacity_categories', 'id')->where('tenant_id', $tenantId),
                new EmbeddedCapacityFitsTypeRule(
                    $this->boolean('default_has_embedded_container') ? $this->input('default_embedded_container_type') : null,
                ),
            ],
        ];
    }

    protected function embeddedDefaultAttributes(): array
    {
        return [
            'default_has_embedded_container' => __('Embedded container'),
            'default_embedded_container_type' => __('Embedded container type'),
            'default_embedded_asset_capacity_category_id' => __('Capacity'),
        ];
    }

    /** Form posts send "1"/"0"/"true" strings — coerce before validating. */
    protected function normalizeEmbeddedDefaults(): void
    {
        if ($this->has('default_has_embedded_container')) {
            $this->merge([
                'default_has_embedded_container' => filter_var($this->input('default_has_embedded_container'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
