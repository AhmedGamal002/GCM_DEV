<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * FRD V01.14 §1.13 "التعريف وصلاحية الانشاء وسياسات التعطيل" and the role
 * pages:
 *  - create / edit / deactivate: the System Admin ONLY (not Data Entry —
 *    unlike every other module, the FRD gives Data Entry no access to POs at
 *    all, not even to view them);
 *  - view / export: System Admin and the (system) Auditor;
 *  - a client Project Manager sees the POs of the projects they are assigned
 *    to, with every amount (decision of 2026-11-09), through the one
 *    visibility rule Project::scopeVisibleTo(). They can't export (the
 *    export button is on the staff page);
 *  - everyone else — Data Entry, client Project Auditor, contractor user,
 *    driver — gets nothing, not even a read.
 *
 * Tenant isolation is guaranteed by BelongsToTenant before these run.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'auditor', 'client_project_manager']);
    }

    public function view(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        if ($actor->hasAnyRole(['system_admin', 'auditor'])) {
            return true;
        }

        return $actor->hasRole('client_project_manager')
            && Project::query()->whereKey($purchaseOrder->project_id)->visibleTo($actor)->exists();
    }

    public function export(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'auditor']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function update(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function updateStatus(User $actor, PurchaseOrder $purchaseOrder): bool
    {
        return $actor->hasRole('system_admin');
    }
}
