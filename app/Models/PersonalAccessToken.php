<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum's own token-validation path (Guard::isValidAccessToken(), used
 * for non-stateful/bearer-token requests — i.e. the mobile app path, see
 * LoginController's docblock) resolves the token owner via this model's
 * tokenable() relation directly, completely outside the 'users' auth
 * provider layer. TenantUnawareEloquentUserProvider only exempts the
 * guard's own retrieveById()/retrieveByCredentials() calls — it has no
 * effect here, so without this override a bearer-token request 500s
 * before EnsureTenant ever runs: User's BelongsToTenant scope demands a
 * tenant that can't possibly be bound yet (same bootstrapping problem
 * TenantUnawareEloquentUserProvider exists to solve, just via a
 * different framework code path).
 *
 * Registered via Sanctum::usePersonalAccessTokenModel() in
 * AppServiceProvider::boot().
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable(): MorphTo
    {
        return parent::tokenable()->withoutGlobalScope(BelongsToTenant::class);
    }
}
