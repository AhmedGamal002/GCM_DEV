<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * Only `show` this week — pulled forward from Week 2 so a query on
 * User (which runs through BelongsToTenant/TenantScope) gives the
 * tenant-isolation Feature test something real to hit over HTTP.
 * Full CRUD lands in Week 2.
 *
 * Deliberately NOT implicit route-model binding: Laravel's
 * SubstituteBindings middleware runs as part of the global 'api'
 * middleware group, before this route's own 'tenant' middleware gets a
 * chance to bind app('tenant') — so an implicit User $user parameter
 * would throw TenantContextMissingException on every request, even for
 * a user's own tenant. Looking the model up explicitly inside the
 * action body runs after all middleware, once the tenant is bound.
 */
class UserController extends Controller
{
    public function show(int $user)
    {
        $user = User::findOrFail($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->getRoleNames(),
        ]);
    }
}
