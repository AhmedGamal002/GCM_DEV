<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Domain\Trips\Exceptions\TripHistoryIsAppendOnlyException;
use App\Domain\Trips\Support\TripEventBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a trip's permanent history (decision ج8) — append-only like
 * the quota ledger: the model refuses `save()` on an existing row and
 * `delete()`, and its builder refuses mass update / delete. Written only
 * through TripHistory::record(), inside the transaction of the action it
 * describes. Shown to GCM staff only (system admin, data entry, auditor).
 *
 * `changes` holds what an edit changed, field => {from, to}, as readable
 * values (names, codes, numbers) taken when it happened.
 */
class TripEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    // What happened — one per action, plus the side notes they write.
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const CLIENT_APPROVED = 'client_approved';

    public const CLIENT_REJECTED = 'client_rejected';

    public const OBJECTION_ACCEPTED = 'objection_accepted';

    public const OBJECTION_REJECTED = 'objection_rejected';

    public const FINAL_APPROVED = 'final_approved';

    public const CANCELLED = 'cancelled';

    public const RECYCLE_RECEIPT_ATTACHED = 'recycle_receipt_attached';

    public const CLIENT_SIGNATURE_UPLOADED = 'client_signature_uploaded';

    public const QUOTA_RECHARGED = 'quota_recharged';

    // Part 7.3: GCM sent the client an e-mail reminder to approve (ج17) — what the one-hour gap between reminders reads.
    public const CLIENT_REMINDED = 'client_reminded';

    public const ASSET_MOVED = 'asset_moved';

    public const ASSET_MOVEMENT_SKIPPED = 'asset_movement_skipped';

    public const ASSET_MOVEMENT_REVERSED = 'asset_movement_reversed';

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw TripHistoryIsAppendOnlyException::update());
        static::deleting(fn () => throw TripHistoryIsAppendOnlyException::delete());
    }

    /** @param  \Illuminate\Database\Query\Builder  $query */
    public function newEloquentBuilder($query): Builder
    {
        return new TripEventBuilder($query);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
