<?php

namespace App\Domain\Users\Exceptions;

use RuntimeException;

/**
 * ARCHITECTURE.md: the System Admin role is "غير قابل للاستنساخ أو
 * التعطيل" — not cloneable, not deactivatable. This is an absolute
 * invariant, not a permission check: even the System Admin themselves
 * cannot deactivate their own account while holding the role. The only
 * way out is reassigning the role to someone else first (which itself is
 * gated by OnlyOneSystemAdminPerTenantRule).
 */
class CannotDeactivateSystemAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The System Admin account cannot be deactivated while holding that role.');
    }
}
