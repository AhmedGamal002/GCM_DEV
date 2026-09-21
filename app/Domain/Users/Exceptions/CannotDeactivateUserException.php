<?php

namespace App\Domain\Users\Exceptions;

use RuntimeException;

/**
 * FRD: "حالة التعطيل – Deactivated مسؤولية مدير النظام فقط" — unlike
 * create/edit/on_vacation (system_admin OR data_entry), deactivating a
 * user, or reactivating one that's already deactivated, is system_admin
 * only. Distinct from CannotDeactivateSystemAdminException, which guards
 * a different invariant (the System Admin account itself can never be
 * deactivated by anyone).
 */
class CannotDeactivateUserException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only a System Admin can deactivate or reactivate a user.');
    }
}
