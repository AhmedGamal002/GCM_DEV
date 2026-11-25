<?php

namespace Tests\Feature\Dashboard;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.3 — the dashboard page (Blade shell): every signed-in tenant user opens it, it carries the blocks the API
 * fills, the driver (and only the driver) gets the "Add Trip" button, and a guest is sent to login.
 */
class DashboardPageTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('everyone')]
    public function test_every_role_opens_the_dashboard_with_its_blocks(string $who): void
    {
        $user = match ($who) {
            'admin' => $this->admin,
            'dataEntry' => $this->dataEntry,
            'auditor' => $this->auditor,
            'client' => $this->clientManager,
            'driver' => $this->driver->user,
        };

        $this->actingAs($user, 'web')->get('/dashboard')->assertOk()
            ->assertSee('dashboardConfig', false)
            ->assertSee('id="db-trips-chart"', false)
            ->assertSee('id="db-services-chart"', false)
            ->assertSee('id="db-notifications"', false);
    }

    public static function everyone(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client' => ['client'], 'driver' => ['driver']];
    }

    public function test_only_the_driver_gets_the_add_trip_button(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/dashboard')->assertOk()->assertSee(route('app-driver-trip-add'), false);
    }

    public function test_staff_do_not_get_the_add_trip_button_of_the_driver(): void
    {
        $this->actingAs($this->admin, 'web')->get('/dashboard')->assertOk()->assertDontSee(route('app-driver-trip-add'), false);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect();
    }
}
