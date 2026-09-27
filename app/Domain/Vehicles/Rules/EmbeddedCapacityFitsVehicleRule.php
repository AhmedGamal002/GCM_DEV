<?php

namespace App\Domain\Vehicles\Rules;

use App\Models\AssetCapacityCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * FRD §1.5.3 — a vehicle's embedded container capacity must be one the
 * fleet can actually field for that vehicle category. "Can field" is
 * derived from the asset pool: there must be at least one asset of the
 * chosen kind (container / tank), filed under the chosen capacity, that
 * is tagged compatible with the vehicle's category (see
 * AssetCapacityCategory::scopeCompatibleWithVehicle).
 *
 * Cross-field rule: the vehicle category id and the embedded container
 * type are passed in, since a Rule only sees its own attribute's value.
 * Skips itself entirely when the vehicle has no embedded container.
 */
class EmbeddedCapacityFitsVehicleRule implements ValidationRule
{
    public function __construct(
        private readonly ?int $vehicleCategoryId,
        private readonly ?string $assetType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->vehicleCategoryId || ! $this->assetType || blank($value)) {
            return;
        }

        $ok = AssetCapacityCategory::query()
            ->whereKey($value)
            ->compatibleWithVehicle($this->vehicleCategoryId, $this->assetType)
            ->exists();

        if (! $ok) {
            $fail(__('There is no compatible :type capacity recorded for the selected vehicle category.', [
                'type' => $this->assetType === 'tank' ? __('Tank') : __('Container'),
            ]));
        }
    }
}
