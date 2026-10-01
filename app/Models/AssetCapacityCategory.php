<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
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
     * Whether this capacity can be given to something of the given type —
     * an asset, or a vehicle's embedded container. `both` matches either.
     */
    public function fitsType(string $assetType): bool
    {
        return $this->applies_to === 'both' || $this->applies_to === $assetType;
    }
}
