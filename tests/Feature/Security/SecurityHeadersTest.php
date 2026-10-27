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
