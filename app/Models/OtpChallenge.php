<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One e-mailed one-time code (part 7.3). See App\Domain\Otp\OtpService for
 * the rules and the migration for what each column means. Only the keyed
 * hash of the code is stored.
 *
 * $guarded keeps the ids out of mass assignment; everything else is set by
 * OtpService.
 */
class OtpChallenge extends Model
{
    use BelongsToTenant, Prunable;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A code that can still be tried: not replaced, not used up, not accepted yet. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('invalidated_at')->whereNull('verified_at');
    }

    /** Old rows are of no use: `php artisan model:prune` removes the ones older than a week. */
    public function prunable(): Builder
    {
        return static::query()->withoutGlobalScopes()->where('created_at', '<', now()->subWeek());
    }
}
