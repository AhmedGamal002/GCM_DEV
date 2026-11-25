<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification for one user (part 8.2). Created only by the notifiers in App\Domain\Notifications
 * (a plain insert per recipient); the only thing a user changes is `read_at`. Everyone reads their own rows only:
 * every query goes through `forUser()`.
 */
class UserNotification extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_notifications.user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
