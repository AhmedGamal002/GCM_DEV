<?php

namespace App\Domain\Vehicles\Rules;

use App\Models\AssetCapacityCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * FRD §1.5.3 — a vehicle's embedded container capacity is picked from the
 * asset capacities that have been created ("التصنيفات الخاصة بسعات الأصول
 * التي تم انشائها"), narrowed to the chosen kind: a container takes the
 * capacities that apply to containers, a tank those that apply to tanks,
 * and a capacity that applies to both fits either.
 *
 * It does NOT depend on the vehicle's category or on which assets exist —
 * an embedded container is part of the vehicle, not of the asset pool.
 *
 * Cross-field rule: the embedded container type is passed in, since a Rule
 * only sees its own attribute's value. Skips itself when the vehicle has no
 * embedded container (no type).
 */
class EmbeddedCapacityFitsTypeRule implements ValidationRule
{
    public function __construct(private readonly ?string $assetType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->assetType || blank($value)) {
            return;
        }

        // An unknown / other-tenant id is reported by the `exists` rule.
        $capacity = AssetCapacityCategory::find($value);

        if ($capacity && ! $capacity->fitsType($this->assetType)) {
            $fail(__('The selected capacity doesn\'t apply to a :type.', [
                'type' => $this->assetType === 'tank' ? __('Tank') : __('Container'),
            ]));
        }
    }
}
