<?php

namespace App\Domain\Users\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces ARCHITECTURE.md's "System Admin — دور واحد فقط" rule: exactly
 * one system_admin per tenant. Invoked from the FormRequest whenever the
 * submitted role list contains 'system_admin', on both create and update
 * (role-sync) paths — "not cloneable" falls out of this rule applying to
 * every mutation path, not just creation.
 */
class OnlyOneSystemAdminPerTenantRule implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array('system_admin', (array) $value, true)) {
            return;
        }

        $query = User::role('system_admin');

        if ($this->ignoreUserId) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('This tenant already has a System Admin. Only one is allowed.');
        }
    }
}
