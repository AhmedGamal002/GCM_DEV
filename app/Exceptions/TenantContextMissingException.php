<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-scoped model is queried or created without a tenant
 * bound in the container. Fail-closed by design: an unscoped query here
 * would silently leak data across tenants, which is worse than a hard error.
 */
class TenantContextMissingException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(
            "No tenant is bound in the container while accessing [{$model}]. ".
            "Bind one via app()->instance('tenant', \$tenant) before running this code, ".
            "or explicitly call withoutGlobalScope(\App\Concerns\BelongsToTenant::class) ".
            'for an intentional cross-tenant (Platform) query.'
        );
    }
}
