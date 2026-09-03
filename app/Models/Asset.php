<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * FRD §1.7.3 — a container (solid waste) or tank (liquid waste) in the
 * company's asset pool, one of the components of a trip.
 *
 * $fillable excludes:
 *  - tenant_id       — stamped by BelongsToTenant
 *  - operational_status — only ever set through UpdateAssetStatusAction
 *  - updated_by      — set explicitly by the create/update actions
 *
 * Per the FRD, once created only the `name` is editable — capacity,
 * type, affiliation and the compatible vehicle categories are locked.
 * That rule lives in UpdateAssetRequest / UpdateAssetAction; the model
 * still lists the create-time fields as fillable.
 */
class Asset extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'asset_type',
        'asset_capacity_category_id',
        'affiliation',
        'contractor_id',
        'purchase_date',
        'additional_data',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
        ];
    }

    public function capacityCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCapacityCategory::class, 'asset_capacity_category_id');
    }

    public function compatibleVehicleCategories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'asset_vehicle_categories');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
