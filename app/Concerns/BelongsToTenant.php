<?php

namespace App\Concerns;

use App\Exceptions\TenantContextMissingException;
use App\Models\Scopes\TenantScope;

/**
 * The backbone of row-level multi-tenancy isolation. Applied to every
 * tenant-scoped model.
 *
 * "Current tenant" is resolved from a container binding (app('tenant'),
 * set once per request by EnsureTenant, or explicitly by seeders/console
 * commands/tests) rather than from Auth::user()->tenant_id directly —
 * console commands, queued jobs and seeders have no HTTP request/session,
 * and Platform (Super Admin) requests must never inherit a stale tenant
 * from an unrelated authenticated guard.
 *
 * Fail-closed: any query on a model using this trait throws
 * TenantContextMissingException when no tenant is bound, rather than
 * silently returning unscoped rows. Use
 * Model::withoutGlobalScope(BelongsToTenant::class) for the rare,
 * intentional cross-tenant query (Platform-side code).
 *
 * tenant_id is deliberately NOT added to any model's $fillable — it is
 * only ever set via the auto-stamp below or explicit server-side code,
 * never via mass assignment from request input.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Registered under the trait's own key (not TenantScope::class,
        // which addGlobalScope(new TenantScope) would use by default) so
        // every withoutGlobalScope(BelongsToTenant::class) call in the app
        // actually matches this scope.
        static::addGlobalScope(BelongsToTenant::class, new TenantScope);

        static::creating(function ($model) {
            if (is_null($model->tenant_id)) {
                if (! app()->bound('tenant')) {
                    throw new TenantContextMissingException($model::class);
                }

                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
