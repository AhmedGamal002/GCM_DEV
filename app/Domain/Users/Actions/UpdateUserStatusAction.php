<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Exceptions\CannotDeactivateSystemAdminException;
use App\Domain\Users\Exceptions\CannotDeactivateUserException;
use App\Models\User;

/**
 * Mirrors UpdateVehicleStatusAction/UpdateAssetStatusAction: the Policy
 * gates the request to (system_admin / data_entry), and this second
 * check narrows deactivating — or reactivating something that was
 * deactivated — to system_admin only (FRD: "حالة التعطيل مسؤولية مدير
 * النظام فقط"). Moving to/from on_vacation stays open to data_entry.
 */
class UpdateUserStatusAction
{
    public function execute(User $user, string $status, User $actor): User
    {
        if ($status === 'deactivated' && $user->hasRole('system_admin')) {
            throw new CannotDeactivateSystemAdminException;
        }

        $touchesDeactivation = $status === 'deactivated' || $user->status === 'deactivated';

        if ($touchesDeactivation && ! $actor->hasRole('system_admin')) {
            throw new CannotDeactivateUserException;
        }

        $user->status = $status;
        $user->updated_by = $actor->id;
        $user->save();

        return $user;
    }
}
