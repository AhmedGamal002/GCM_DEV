<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening headers on every response (pages, API, file downloads).
 *
 * No `script-src` / `style-src` on purpose: the Vuexy templates, the page-level Blade `<script>` blocks and the
 * inline styles all rely on inline code, so a content policy with those directives would break the panel. What is
 * pinned instead are the directives that cost nothing: nobody may frame the app (clickjacking), nobody may point a
 * `<base>` or a form at another origin (injected markup cannot redirect a login form), and plugins stay off.
 * A header a controller already set (e.g. a download that needs its own) is left alone.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // A download served as text/html would otherwise be sniffed and rendered; an upload can never run as a page.
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The trip pages take a live photo and a live location (decision ج12: the driver's form — and the staff's
            // optional ones — use the camera and the GPS): allowed for this site only, never for an embedded third party.
            // `camera=()` here switched both off for every page, so the browser refused without even asking.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=()',
            'Content-Security-Policy' => "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'",
        ];

        // HTTPS only. No includeSubDomains: the parent domain also hosts other, unrelated subdomains.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
