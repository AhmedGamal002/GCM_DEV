<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * FRD §1.7.3: creating/editing assets is (System Admin / Data Entry).
 * Auditor is view/export only. Driver has no asset-management access.
 *
 * Tenant isolation is already guaranteed by BelongsToTenant before any
 * of these run — a user cannot load another tenant's Asset.
 *
 * The finer status split (on_maintenance = admin/data_entry; deactivate
 * and reactivate-from-deactivated = admin only) lives in
 * UpdateAssetStatusAction, not here.
 */
class AssetPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, Asset $asset): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function export(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function update(User $actor, Asset $asset): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    /** FRD V01.14 §1.7.3 "Insert an asset into a project" — class-level, the asset is picked on the page. */
    public function insertIntoProject(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function updateStatus(User $actor, Asset $asset): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
