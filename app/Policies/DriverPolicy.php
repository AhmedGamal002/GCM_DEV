<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

/**
 * Same ownership rule as UserPolicy — driver management (tenant side) is
 * system_admin-only. A driver is still a User under the hood, so this
 * mirrors UserPolicy's shape exactly.
 */
class DriverPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function view(User $actor, Driver $target): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function update(User $actor, Driver $target): bool
    {
        return $actor->hasRole('system_admin');
    }
}
