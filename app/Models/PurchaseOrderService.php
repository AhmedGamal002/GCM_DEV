<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.13 — one line ("بند") of a purchase order: a main service
 * and its quota, counted one of two ways:
 *  - trips_only           — max trips × trip cost
 *  - trips_and_ancillary  — the same, plus an ancillary-services cost per
 *                           trip, whose total (max trips × ancillary cost)
 *                           is watched on its own
 * The totals are computed (maxTripCost(), maxAncillaryCost()), never stored.
 * What has been consumed comes from the ledger — see QuotaBalances.
 *
 * A trip stores its own cost when it is recorded, so changing trip_cost here
 * only affects the trips recorded after it (decision ب3).
 *
 * $fillable excludes tenant_id (BelongsToTenant) and purchase_order_id /
 * main_service_id, which the PO actions set and PurchaseOrderEditability
 * guards.
 */
class PurchaseOrderService extends Model
{
    use BelongsToTenant, HasFactory;

    public const METHOD_TRIPS_ONLY = 'trips_only';

    public const METHOD_TRIPS_AND_ANCILLARY = 'trips_and_ancillary';

    public const METHODS = [self::METHOD_TRIPS_ONLY, self::METHOD_TRIPS_AND_ANCILLARY];

    protected $fillable = [
        'quota_method',
        'max_trips',
        'trip_cost',
        'ancillary_cost_per_trip',
        'alert_percent',
    ];

    protected function casts(): array
    {
        return [
            'max_trips' => 'integer',
            'trip_cost' => 'decimal:2',
            'ancillary_cost_per_trip' => 'decimal:2',
            'alert_percent' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function mainService(): BelongsTo
    {
        return $this->belongsTo(MainService::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(QuotaLedgerEntry::class);
    }

    /** Whether the ancillary-services cost is part of this line's quota. */
    public function tracksAncillary(): bool
    {
        return $this->quota_method === self::METHOD_TRIPS_AND_ANCILLARY;
    }

    /** "اجمالي الحد الأقصى من تكلفة الرحلات" = max trips × trip cost. */
    public function maxTripCost(): float
    {
        return round($this->max_trips * (float) $this->trip_cost, 2);
    }

    /** "اجمالي الحد الأقصى من تكلفة الخدمات المساعدة" = max trips × ancillary cost per trip (0 for trips only). */
    public function maxAncillaryCost(): float
    {
        return $this->tracksAncillary() ? round($this->max_trips * (float) $this->ancillary_cost_per_trip, 2) : 0.0;
    }
}
