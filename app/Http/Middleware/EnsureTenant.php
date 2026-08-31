<?php

namespace App\Http\Middleware;

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
 *  2. The authenticated user's tenant_id, resolved via guard `sanctum`
 *     rather than `web` directly — Sanctum's guard transparently covers
 *     BOTH auth modes this app supports (see LoginController): a
 *     stateful/cookie request falls through to the `web` session guard
 *     exactly as before, while a bearer-token (mobile) request resolves
 *     via the token's tokenable. `Auth::guard('web')` alone would leave
 *     every token-authenticated request with no tenant bound at all.
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
        $user = Auth::guard('sanctum')->user();

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
