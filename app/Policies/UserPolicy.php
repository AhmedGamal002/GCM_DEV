<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (tenant side) is system_admin-only — see
 * ARCHITECTURE.md §3.5/§3.7. data_entry/auditor/driver get 403 on every
 * /api/v1/users* route. Tenant isolation itself is already guaranteed by
 * BelongsToTenant before any of these methods run — a system_admin
 * literally cannot load another tenant's User row to begin with.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->hasRole('system_admin');
    }
}
