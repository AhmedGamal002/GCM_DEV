<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product is "GLOBAL CLEAR MISSION" / "الرسالة الواضحة العالمية" with the
 * GCM logo — in the sidebar, the sign-in screens, the tab title and the page
 * loader. The template's own name ("Digitswat") must not show through.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private const LOGO = 'assets/img/branding/logo-light.png';

    public function test_the_logo_file_is_shipped(): void
    {
        $this->assertFileExists(public_path(self::LOGO));
    }

    public function test_the_sign_in_page_shows_the_brand_logo_and_loader_and_not_the_template_name(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('GLOBAL CLEAR MISSION', $html);
        $this->assertStringContainsString(self::LOGO, $html);
        $this->assertStringContainsString('id="gcm-page-loader"', $html);
        $this->assertStringNotContainsString('Digitswat', $html);
        $this->assertMatchesRegularExpression('#<title>[^<]*\| GLOBAL CLEAR MISSION</title>#', $html);
    }

    public function test_the_name_follows_the_language_switcher(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('الرسالة الواضحة العالمية', $html);
        $this->assertStringNotContainsString('GLOBAL CLEAR MISSION', $html);
    }

    public function test_platform_pages_stay_english_whatever_the_session_language(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->get('/platform/login')->assertOk()->getContent();

        $this->assertStringContainsString('GLOBAL CLEAR MISSION', $html);
        $this->assertStringNotContainsString('الرسالة الواضحة العالمية', $html);
    }

    public function test_the_sidebar_shows_the_brand_after_login(): void
    {
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $html = $this->actingAs($admin, 'web')->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('gcm-brand-logo', $html);
        $this->assertStringContainsString('GLOBAL CLEAR MISSION', $html);
        $this->assertStringNotContainsString('Digitswat', $html);
    }
}
