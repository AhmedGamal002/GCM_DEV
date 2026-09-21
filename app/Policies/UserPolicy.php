<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (tenant side) is (system_admin / data_entry) — FRD:
 * "(انشاء / تعديل) الحسابات مسؤولية (مدير النظام / مدخل البيانات)",
 * same pattern already correct in VehiclePolicy/AssetPolicy. auditor/
 * driver still get 403 on every /api/v1/users* route — the FRD never
 * lists Users among auditor's (عرض/تصدير) sections. Tenant isolation
 * itself is already guaranteed by BelongsToTenant before any of these
 * methods run — an actor literally cannot load another tenant's User row
 * to begin with.
 *
 * The finer status split (on_vacation = admin/data_entry; deactivated
 * and reactivate-from-deactivated = admin only) lives in
 * UpdateUserStatusAction, not here — same shape as
 * UpdateVehicleStatusAction/UpdateAssetStatusAction.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
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
        return $this->canManage($actor, $target) && ! $target->hasRole('driver');
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    /**
     * data_entry may manage accounts, but never the tenant's system_admin
     * — otherwise they could reset its password (account takeover) or
     * demote it via the roles field. FRD: the System Admin is the top
     * role and "لا يمكن تعطيله او استنساخه".
     */
    private function canManage(User $actor, User $target): bool
    {
        if ($actor->hasRole('system_admin')) {
            return true;
        }

        return $actor->hasRole('data_entry') && ! $target->hasRole('system_admin');
    }
}
