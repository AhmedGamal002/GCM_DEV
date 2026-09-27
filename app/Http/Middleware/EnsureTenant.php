<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant and binds it into the container as 'tenant'
 * for BelongsToTenant/TenantScope to read.
 *
 * Resolution order:
 *  1. Subdomain: host label matched against tenants.slug/domain (skipping
 *     reserved hosts like www/api/platform/localhost). Structurally ready
 *     for real tenant subdomains, though not exercised yet since local dev
 *     runs on a single host.
 *  2. The authenticated user's tenant_id — covers BOTH auth modes this
 *     app supports (see LoginController): guard `web` for a
 *     stateful/cookie request, or the bearer token's tokenable for a
 *     mobile-style request. Resolved via currentUser() below, NOT
 *     `Auth::guard('sanctum')->user()` — see that method's docblock for
 *     a real bug that caused.
 *  3. Otherwise left unbound (public/guest routes — login pages etc.).
 *
 * If a subdomain resolves to a tenant that conflicts with the
 * authenticated user's own tenant, the credential is force-revoked and
 * the request rejected — defends against a session cookie (or, for
 * mobile, a bearer token) issued for one tenant being replayed against
 * another's.
 */
class EnsureTenant
{
    private const RESERVED_HOSTS = ['www', 'api', 'platform', 'localhost'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromSubdomain($request);
        $user = $this->currentUser($request);

        if ($tenant) {
            if ($user && $user->tenant_id !== $tenant->id) {
                $this->revokeAuthentication($request, $user);

                abort(403, 'Session does not belong to this tenant.');
            }
        } elseif ($user) {
            $tenant = Tenant::find($user->tenant_id);

            if (! $tenant || $tenant->status !== 'active') {
                $this->revokeAuthentication($request, $user);

                abort(403, 'Your organization account is not active.');
            }
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }

    /**
     * Deliberately NOT `Auth::guard('sanctum')->user()`. Sanctum's guard
     * is a Laravel RequestGuard, which caches its resolved user on the
     * GUARD INSTANCE itself, not per-request — harmless for a real HTTP
     * request (a fresh process gets a fresh guard instance every time),
     * but a real bug in Feature tests: the app container (and every
     * guard instance in it) survives across multiple $this->post()/get()
     * calls within ONE test method, so a test that does
     * `actingAs($userA)->post(...); actingAs($userB, 'web')->post(...);`
     * got $userA's STALE cached identity on the second call — found live
     * via a colleague's VehicleUniquePlateTest failing with a
     * cross-tenant plate collision that should never have been possible.
     * `actingAs()` only resets the guard NAME you pass it (here, 'web'),
     * never 'sanctum' — so the stale RequestGuard cache was never
     * touched. This resolves the same two auth modes without going
     * through that cached guard at all: guard('web') is a plain
     * SessionGuard (actingAs() correctly overrides its user every call,
     * no caching issue), and a bearer token is validated with a fresh
     * query every time.
     */
    private function currentUser(Request $request)
    {
        if ($user = Auth::guard('web')->user()) {
            return $user;
        }

        if ($token = $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($token);

            // withAccessToken() attaches the token so currentAccessToken()
            // works later (revokeAuthentication() needs it to delete the
            // token on a tenant mismatch/suspension) — Sanctum's own Guard
            // does the same before returning the tokenable.
            return $accessToken?->tokenable?->withAccessToken($accessToken);
        }

        return null;
    }

    /**
     * Session-based auth gets the original web-guard logout + session
     * invalidation. A bearer-token request has no session to invalidate —
     * the equivalent action is deleting the token itself, so it can't be
     * replayed after this rejection.
     */
    private function revokeAuthentication(Request $request, $user): void
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return;
        }

        $user->currentAccessToken()?->delete();
    }

    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $label = explode('.', $host)[0];

        if (in_array($label, self::RESERVED_HOSTS, true)) {
            return null;
        }

        return Tenant::where('slug', $label)->orWhere('domain', $host)->first();
    }
}
