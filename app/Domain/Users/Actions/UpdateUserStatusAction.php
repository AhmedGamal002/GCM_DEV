<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Exceptions\CannotDeactivateSystemAdminException;
use App\Models\User;

class UpdateUserStatusAction
{
    public function execute(User $user, string $status): User
    {
        if ($status === 'deactivated' && $user->hasRole('system_admin')) {
            throw new CannotDeactivateSystemAdminException;
        }

        $user->update(['status' => $status]);

        return $user;
    }
}
