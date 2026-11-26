<?php

namespace Tests\Feature\Reports;

use App\Models\Contractor;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.4 — the reports page (Blade shell): staff and the client's project manager get the filters, the charts and
 * the trips summary; the client's project auditor the filters and the Excel without charts; the driver and the
 * contractor user nothing — and the menu says the same. One actor per test.
 */
class ReportPagesTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('full')]
    public function test_staff_and_the_clients_manager_get_the_whole_page(string $who): void
    {
        $user = $who === 'client' ? $this->clientManager : $this->{$who};

        $this->actingAs($user, 'web')->get('/app/reports')->assertOk()
            ->assertSee('id="rp-filters"', false)
            ->assertSee('id="rp-timeline"', false)
            ->assertSee('id="rp-table"', false)
            ->assertSee('id="rp-export"', false);
    }

    public static function full(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client manager' => ['client']];
    }

    public function test_the_clients_project_auditor_gets_the_excel_without_charts(): void
    {
        $this->actingAs($this->clientAuditor, 'web')->get('/app/reports')->assertOk()
            ->assertSee('id="rp-export"', false)
            ->assertDontSee('id="rp-timeline"', false);
    }

    public function test_the_driver_cannot_open_it(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/reports')->assertForbidden();
    }

    public function test_the_contractor_user_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->contractorUser(Contractor::factory()->create())->create(), 'web')->get('/app/reports')->assertForbidden();
    }

    public function test_the_menu_shows_reports_to_the_client(): void
    {
        $this->actingAs($this->clientAuditor, 'web')->get('/dashboard')->assertOk()->assertSee('app/reports', false);
    }

    public function test_the_menu_hides_reports_from_the_driver(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/dashboard')->assertOk()->assertDontSee('app/reports', false);
    }
}
