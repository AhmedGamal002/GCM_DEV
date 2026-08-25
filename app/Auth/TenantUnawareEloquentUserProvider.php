<?php

namespace App\Auth;

use App\Concerns\BelongsToTenant;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Resolving "who is the authenticated user" must happen BEFORE the tenant
 * is known — EnsureTenant's second resolution branch derives the current
 * tenant FROM the authenticated user's tenant_id. That's an unavoidable
 * bootstrapping order: session/credential/remember-token lookups on the
 * `users` provider always run before any tenant is bound in the
 * container, so they must bypass BelongsToTenant's scope by construction,
 * not as an incidental workaround.
 *
 * Every other query in the app (route model binding, business queries)
 * goes through the normal scoped Eloquent query and stays fail-closed —
 * only auth's own bootstrap lookups are exempt here.
 */
class TenantUnawareEloquentUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(BelongsToTenant::class);
    }
}
