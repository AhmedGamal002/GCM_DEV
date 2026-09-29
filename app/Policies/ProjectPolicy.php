<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * FRD V01.14 §1.12: creating / editing / deactivating a client project is
 * (System Admin / Data Entry) — Data Entry has admin parity here. Auditor
 * is view/export only. Driver has no access.
 *
 * Tenant isolation is already guaranteed by BelongsToTenant before any of
 * these run — a user cannot load another tenant's Project.
 */
class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, Project $project): bool
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

    public function update(User $actor, Project $project): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function updateStatus(User $actor, Project $project): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
