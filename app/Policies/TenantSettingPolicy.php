<?php

namespace App\Policies;

use App\Models\User;

/**
 * The General Settings page and its API are for the system admin only (client request) — data entry,
 * the auditor and every other role neither see nor change them. The thresholds themselves reach the
 * other roles only as part of the driver / vehicle records they can already view.
 */
class TenantSettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function update(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }
}
