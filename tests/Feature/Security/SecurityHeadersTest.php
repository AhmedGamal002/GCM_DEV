<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_the_hardening_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    /**
     * The trip pages need the camera and the GPS (a live photo and a live location, decision ج12). A policy of
     * `camera=()` switches them off for every page, and the browser refuses without even asking — so they are
     * allowed for this site only, and everything the app never uses stays off.
     */
    public function test_the_camera_and_the_location_are_allowed_for_this_site_only(): void
    {
        $policy = $this->get('/login')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringNotContainsString('camera=*', $policy);
        $this->assertStringNotContainsString('geolocation=*', $policy);

        foreach (['microphone=()', 'payment=()', 'usb=()'] as $off) {
            $this->assertStringContainsString($off, $policy);
        }
    }

    public function test_api_responses_and_errors_carry_them_too(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertUnauthorized();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_sent_over_https_only(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_trusted_hosts_are_the_app_url_its_subdomains_and_the_configured_list(): void
    {
        config(['app.url' => 'https://portal.example.org', 'app.trusted_hosts' => ['tenant.example.net']]);

        $hosts = app(TrustHosts::class)->hosts();

        $this->assertContains('^(.+\.)?portal\.example\.org$', $hosts);
        $this->assertContains('^tenant\.example\.net$', $hosts);

        TrustHosts::flushState();
    }
}
