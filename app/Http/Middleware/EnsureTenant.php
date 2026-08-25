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
 *  2. The authenticated `web` guard user's tenant_id.
 *  3. Otherwise left unbound (public/guest routes — login pages etc.).
 *
 * If a subdomain resolves to a tenant that conflicts with the
 * authenticated user's own tenant, the session is force-logged-out and the
 * request rejected — defends against a session cookie issued on one
 * tenant's subdomain being replayed against another's.
 */
class EnsureTenant
{
    private const RESERVED_HOSTS = ['www', 'api', 'platform', 'localhost'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromSubdomain($request);

        if ($tenant) {
            if (Auth::guard('web')->check() && Auth::guard('web')->user()->tenant_id !== $tenant->id) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Session does not belong to this tenant.');
            }
        } elseif (Auth::guard('web')->check()) {
            $tenant = Tenant::find(Auth::guard('web')->user()->tenant_id);

            if (! $tenant || $tenant->status !== 'active') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Your organization account is not active.');
            }
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
        }

        return $next($request);
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
