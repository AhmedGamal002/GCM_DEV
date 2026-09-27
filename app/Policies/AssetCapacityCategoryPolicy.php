<?php

namespace App\Policies;

use App\Models\AssetCapacityCategory;
use App\Models\User;

/**
 * FRD §1.7.2: create/edit a capacity category is (System Admin / Data
 * Entry); auditor views only. There is no delete and no deactivate.
 *
 * NOTE: the list endpoint (index) is intentionally NOT gated — it's
 * read-only reference data that every tenant role needs for the vehicle
 * and asset form dropdowns (a driver reading it is covered by
 * AssetCapacityCategoryReadTest). Only the mutations and the single-row
 * fetch behind the management pages go through this policy.
 */
class AssetCapacityCategoryPolicy
{
    public function view(User $actor, AssetCapacityCategory $category): bool
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

    public function update(User $actor, AssetCapacityCategory $category): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
