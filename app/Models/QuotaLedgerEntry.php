<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Domain\PurchaseOrders\Exceptions\LedgerIsAppendOnlyException;
use App\Domain\PurchaseOrders\Quota\AppendOnlyBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One row of the quota ledger — append-only. A trip that consumes a PO line
 * writes a `consume` row (+1 trip, +its costs); cancelling it, or moving it
 * to another line, writes a `release` row with the opposite deltas that
 * points at the consume it reverses (`reverses_id`, unique). Nothing is ever
 * edited or deleted: the model refuses `save()` on an existing row and
 * `delete()`, and its query builder refuses mass update / delete
 * (AppendOnlyBuilder). The balance of a line is the sum of its rows.
 *
 * Only DeductQuotaAction and ReleaseQuotaAction write here.
 */
class QuotaLedgerEntry extends Model
{
    use BelongsToTenant;

    public const TYPE_CONSUME = 'consume';

    public const TYPE_RELEASE = 'release';

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'trips_delta' => 'integer',
            'trip_cost_delta' => 'decimal:2',
            'ancillary_cost_delta' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw LedgerIsAppendOnlyException::update());
        static::deleting(fn () => throw LedgerIsAppendOnlyException::delete());
    }

    /** @param  \Illuminate\Database\Query\Builder  $query */
    public function newEloquentBuilder($query): Builder
    {
        return new AppendOnlyBuilder($query);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderService::class, 'purchase_order_service_id');
    }

    /** The release row that reversed this consume, if any. */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The consume rows of a trip that no release has reversed yet — what the trip currently holds. */
    public function scopeOpenConsumptionsOf(Builder $query, int $tripId): Builder
    {
        return $query
            ->where('trip_id', $tripId)
            ->where('type', self::TYPE_CONSUME)
            ->whereDoesntHave('reversal');
    }
}
