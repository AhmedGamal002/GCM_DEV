<?php

namespace Tests\Feature\Settings;

use App\Http\Controllers\Web\Dev\ComponentDemoController;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * The try-out page for the location / camera / signature components is a developer aid: not there by default,
 * never in production, and only for the system admin. (The routes are registered at boot, so "switched on" is
 * tested through the controller; "off" is tested through the real route.)
 */
class ComponentDemoPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        return $user;
    }

    public function test_the_page_is_not_there_by_default_even_for_the_system_admin(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/app/dev/components')->assertNotFound();
    }

    public function test_the_controller_refuses_when_the_switch_is_off(): void
    {
        config(['custom.custom.showComponentDemos' => false]);

        $this->expectException(NotFoundHttpException::class);

        (new ComponentDemoController)->index();
    }

    public function test_the_controller_refuses_in_production_even_when_switched_on(): void
    {
        config(['custom.custom.showComponentDemos' => true]);
        $this->app['env'] = 'production';

        $this->expectException(NotFoundHttpException::class);

        (new ComponentDemoController)->index();
    }

    public function test_when_switched_on_outside_production_the_page_renders_the_three_components(): void
    {
        config(['custom.custom.showComponentDemos' => true]);

        $html = (new ComponentDemoController)->index()->render();

        $this->assertStringContainsString('demo-geo-btn', $html);
        $this->assertStringContainsString('demo-cam-btn', $html);
        $this->assertStringContainsString('demo-signature', $html);
    }

    public function test_the_components_wording_is_in_both_languages(): void
    {
        $keys = [
            'Location is not supported on this device.', 'Location only works on a secure (HTTPS) connection.',
            'Location access was denied. Allow it in the browser settings and try again.',
            'Your location is not available right now. Move to an open area and try again.',
            'Finding your location took too long. Try again.', 'The camera is not supported on this device or browser.',
            'The camera only works on a secure (HTTPS) connection.',
            'Camera access was denied. Allow it in the browser settings and try again.',
            'No camera was found on this device.', 'The camera is being used by another app. Close it and try again.',
            'The camera could not be started. Try again.', 'Take photo', 'Retake', 'Use photo', 'Sign here',
            'Draw your signature first.', 'The signature is too large. Clear it and sign again.',
        ];

        $ar = json_decode(file_get_contents(lang_path('ar.json')), true);
        $en = json_decode(file_get_contents(lang_path('en.json')), true);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $en, "en.json lacks: $key");
            $this->assertArrayHasKey($key, $ar, "ar.json lacks: $key");
            $this->assertNotSame($key, $ar[$key], "ar.json has the English text for: $key");
        }
    }

    public function test_the_layout_hands_the_wording_to_the_scripts(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/app/vehicle/list')
            ->assertOk()
            ->assertSee('geo_denied', false)
            ->assertSee('camera_take', false)
            ->assertSee('signature_hint', false);
    }
}
