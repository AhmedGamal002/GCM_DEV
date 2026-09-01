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

    /**
     * A driver's editable record lives entirely under the Drivers module
     * (PATCH /api/v1/drivers/{id}) — never here, regardless of payload.
     * Blocking it at the policy level (not just UserAddController's/the
     * FormRequest's role whitelist) means it holds even if someone posts
     * directly to this endpoint bypassing the UI. See StoreUserRequest's
     * docblock for the bug this prevents (a driver with no `drivers` row).
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->hasRole('system_admin') && ! $target->hasRole('driver');
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->hasRole('system_admin');
    }
}
