<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a completed trip did to one of its containers (decision ج3): the
 * going container moved into the trip's project, or the returning one out of
 * it. Kept so that cancelling the trip — or changing its containers after
 * it was completed — can put the asset back. Written and reversed only by
 * App\Domain\Trips\Support\TripAssetMovement.
 */
class TripAssetMovement extends Model
{
    use BelongsToTenant;

    public const ROLE_GOING = 'going';

    public const ROLE_RETURNING = 'returning';

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
            'reversal_skipped' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
