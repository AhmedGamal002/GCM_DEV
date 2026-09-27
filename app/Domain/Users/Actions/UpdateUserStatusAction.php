<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Exceptions\CannotDeactivateSystemAdminException;
use App\Models\User;

/**
 * FRD V01.14 (changed from V01.09): "(انشاء / تعديل / تعطيل) الحسابات
 * مسؤولية (مدير النظام / مدخل البيانات)" — data_entry may now deactivate
 * and reactivate accounts too, not just move them to/from on_vacation.
 * The one line that never moved between versions: "للنظام مدير واحد
 * فقط... ولا يمكن تعطيله" — the tenant's system_admin itself stays
 * untouchable by anyone, including another system_admin trying to
 * deactivate themselves. The Policy already gates the request to
 * (system_admin / data_entry); this is the second check that holds even
 * if the action is called directly.
 */
class UpdateUserStatusAction
{
    public function execute(User $user, string $status, User $actor): User
    {
        if ($status === 'deactivated' && $user->hasRole('system_admin')) {
            throw new CannotDeactivateSystemAdminException;
        }

        $user->status = $status;
        $user->updated_by = $actor->id;
        $user->save();

        return $user;
    }
}
