<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD §1.7.2 — a capacity band an asset (container / tank) is filed
 * under, carrying BOTH the cubic-metre (CBM) and tonne (TON) equivalents
 * together (not a choice of one unit). Fixed after creation: only the
 * name and notes may be edited — capacity and `applies_to` are locked
 * because they ripple into assets, sub-services and POs.
 *
 * `tenant_id` and `updated_by` are deliberately outside $fillable —
 * stamped by BelongsToTenant / set explicitly in the actions.
 */
class AssetCapacityCategory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'applies_to', 'capacity_cbm', 'capacity_ton', 'additional_data'];

    protected function casts(): array
    {
        return [
            'capacity_cbm' => 'decimal:2',
            'capacity_ton' => 'decimal:2',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Whether this capacity can be assigned to an asset of the given
     * type — `both` matches either.
     */
    public function fitsType(string $assetType): bool
    {
        return $this->applies_to === 'both' || $this->applies_to === $assetType;
    }

    /**
     * Capacities usable as a given vehicle category's embedded container —
     * derived through the asset pool: the tenant must own at least one
     * asset of the requested kind (container / tank), filed under this
     * capacity, that is tagged compatible with that vehicle category.
     *
     * Used by the vehicle form's embedded-capacity dropdown and by
     * EmbeddedCapacityFitsVehicleRule. Deliberately does NOT fall back to
     * "all capacities of that type" — an empty result is the intended
     * signal that no matching stock is recorded.
     */
    public function scopeCompatibleWithVehicle(Builder $query, int $vehicleCategoryId, string $assetType): Builder
    {
        return $query->whereHas('assets', function (Builder $asset) use ($vehicleCategoryId, $assetType) {
            $asset->where('asset_type', $assetType)
                ->whereHas('compatibleVehicleCategories', fn (Builder $vc) => $vc->where('vehicle_categories.id', $vehicleCategoryId));
        });
    }
}
