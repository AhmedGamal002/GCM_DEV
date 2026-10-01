<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (tenant side) is (system_admin / data_entry) — FRD
 * V01.14: "(انشاء / تعديل / تعطيل) الحسابات مسؤولية (مدير النظام / مدخل
 * البيانات)". auditor gets view/export only (V01.14, new: "له صلاحية
 * الاطلاع علي جميع الصفحات (view)... وليس له أي دور في تعديل او انشاء"
 * — auditor used to be fully blocked here under V01.09, which never
 * listed Users among its (عرض/تصدير) sections). driver still gets 403
 * on every /api/v1/users* route. Tenant isolation itself is already
 * guaranteed by BelongsToTenant before any of these methods run — an
 * actor literally cannot load another tenant's User row to begin with.
 *
 * The finer status split (data_entry may now deactivate/reactivate too;
 * only the tenant's system_admin itself stays untouchable by anyone)
 * lives in UpdateUserStatusAction, not here — same shape as
 * UpdateVehicleStatusAction/UpdateAssetStatusAction.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    /**
     * A driver's editable record lives entirely under the Drivers module
     * (PATCH /api/v1/drivers/{id}) — never here, regardless of payload; a
     * client account's under the client accounts endpoint (see updateClient()).
     * Blocking it at the policy level (not just UserAddController's/the
     * FormRequest's role whitelist) means it holds even if someone posts
     * directly to this endpoint bypassing the UI. See StoreUserRequest's
     * docblock for the bug this prevents (a driver with no `drivers` row).
     */
    public function update(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target) && ! $target->hasRole('driver') && ! $target->isClient();
    }

    /**
     * A client account's editable record lives entirely under the client
     * accounts endpoint (PATCH /api/v1/client-users/{id}) — its company,
     * project access and signature/stamp don't exist on the generic form,
     * and that form would re-role it to data_entry/auditor. Same split as
     * drivers (see update()).
     */
    public function updateClient(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target) && $target->isClient();
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
