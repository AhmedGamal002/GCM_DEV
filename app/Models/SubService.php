<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * FRD V01.14 §1.9.2 — a sub-service: what a trip actually delivers to a
 * client. It belongs to one main service, handles one asset type
 * (container / tank), fits a set of asset capacities, sends its waste to a
 * set of intermediate facilities, and may carry a skip colour code and a
 * "requires recycle receipt" flag that trips enforce.
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - main_service_id    — set once by CreateSubServiceAction and locked
 *  - operational_status — only ever set through the status actions
 *  - updated_by         — set explicitly by the actions
 */
class SubService extends Model
{
    use BelongsToTenant, HasFactory;

    public const ASSET_TYPES = ['container', 'tank'];

    /**
     * FRD "Skip Color Code" — a fixed list ("phase 0" component).
     * slug => [English label (also the lang key), swatch colour].
     */
    public const SKIP_COLORS = [
        'designated_yard' => ['Designated Yard', null],
        'white_paper' => ['White — Paper', '#ffffff'],
        'brown_cardboard' => ['Brown — Cardboard', '#8b5a2b'],
        'light_blue_wood' => ['Light Blue — Wood', '#8ecae6'],
        'gray_plastic' => ['Gray — Plastic', '#8d99ae'],
        'red_metal_scrap' => ['Red — Metal Scrap', '#d62828'],
        'black_hazardous' => ['Black — Hazardous', '#111111'],
        'green_general' => ['Green — General', '#2a9d8f'],
        'yellow_food' => ['Yellow — Food', '#ffc300'],
        'light_concrete' => ['Light — Concrete', '#dcd6c8'],
        'green_glass' => ['Green — Glass', '#2d6a4f'],
    ];

    protected $fillable = ['name', 'asset_type', 'requires_recycle_receipt', 'skip_color_code', 'additional_data'];

    protected function casts(): array
    {
        return [
            'requires_recycle_receipt' => 'boolean',
        ];
    }

    public function mainService(): BelongsTo
    {
        return $this->belongsTo(MainService::class);
    }

    /** FRD "Compatible Assets Type & Capacity". */
    public function capacities(): BelongsToMany
    {
        return $this->belongsToMany(AssetCapacityCategory::class, 'sub_service_asset_capacity');
    }

    /** FRD "Waste disposal & recycle facilities". */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(IntermediateFacility::class, 'sub_service_facility');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->operational_status === 'active';
    }

    /**
     * Whether a trip that is still running uses this sub-service. Trips
     * (FRD §1.14) don't exist yet; wire them in here when they land.
     */
    public function hasOngoingTrips(): bool
    {
        return false;
    }

    /**
     * FRD §1.9.2: a sub-service can't be edited or deactivated while it is
     * tied to an ongoing trip, or while its main service is in a PO.
     */
    public function isLocked(): bool
    {
        return $this->hasOngoingTrips() || $this->mainService->hasPurchaseOrders();
    }

    public static function skipColorLabel(?string $slug): ?string
    {
        return $slug === null ? null : __(self::SKIP_COLORS[$slug][0] ?? $slug);
    }
}
