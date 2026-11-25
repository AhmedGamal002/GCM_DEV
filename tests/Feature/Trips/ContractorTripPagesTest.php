<?php

namespace Tests\Feature\Trips;

use App\Models\Contractor;
use App\Models\Driver;
use App\Models\User;

/**
 * Part 7.7 — the contractor user's trip pages (Blade shells): his list and the details, view only. A contractor-user
 * account only; a trip of another contractor is a 404; no add / edit pages; the menu shows him one "Trips › List".
 * One actor per test.
 */
class ContractorTripPagesTest extends TripTestCase
{
    private Contractor $contractor;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contractor = Contractor::factory()->create();
        $this->user = User::factory()->contractorUser($this->contractor)->create();
    }

    public function test_the_contractor_user_opens_his_list_and_the_details_of_his_trip(): void
    {
        $trip = $this->createAwaiting();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $this->contractor->id]);
        $this->actingAs($this->user, 'web');

        $this->get('/app/contractor-trip/list')->assertOk()->assertSee('contractorTripListTranslations', false);
        $this->get("/app/contractor-trip/view/{$trip->id}")->assertOk()->assertSee($trip->code)->assertSee('contractorTripViewConfig', false);
    }

    public function test_the_page_has_no_costs_no_po_number_and_no_write_controls(): void
    {
        $trip = $this->createAwaiting();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $this->contractor->id]);
        $this->actingAs($this->user, 'web');

        $list = $this->get('/app/contractor-trip/list')->assertOk()->getContent();
        $view = $this->get("/app/contractor-trip/view/{$trip->id}")->assertOk()->getContent();

        foreach ([$list, $view] as $html) {
            $this->assertStringNotContainsString('/app/trip/add', $html);
            $this->assertStringNotContainsString('/app/trip/edit', $html);
            $this->assertStringNotContainsString('data-v="cost"', $html);
            $this->assertStringNotContainsString('data-v="purchase_order"', $html);
        }
        $this->assertStringNotContainsString('id="tf-', $view);
    }

    public function test_another_contractors_trip_is_a_404(): void
    {
        $trip = $this->createAwaiting();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => Contractor::factory()->create()->id]);

        $this->actingAs($this->user, 'web')->get("/app/contractor-trip/view/{$trip->id}")->assertNotFound();
    }

    public function test_an_unknown_trip_is_a_404(): void
    {
        $this->actingAs($this->user, 'web')->get('/app/contractor-trip/view/999999')->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('others')]
    public function test_nobody_but_a_contractor_user_opens_them(string $property): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->{$property}, 'web');

        $this->get('/app/contractor-trip/list')->assertForbidden();
        $this->get("/app/contractor-trip/view/{$trip->id}")->assertForbidden();
    }

    public static function others(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client manager' => ['clientManager']];
    }

    public function test_the_driver_cannot_open_them_either(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/contractor-trip/list')->assertForbidden();
    }

    public function test_the_menu_shows_him_a_trips_list_and_nothing_to_add(): void
    {
        $this->actingAs($this->user, 'web')->get('/dashboard')->assertOk()
            ->assertSee('app/contractor-trip/list', false)
            ->assertDontSee('app/trip/list', false)
            ->assertDontSee('app/trip/add', false)
            ->assertDontSee('app/driver-trip/add', false);
    }

    public function test_the_menu_of_the_others_does_not_show_the_contractor_item(): void
    {
        foreach (['admin', 'auditor'] as $property) {
            $this->actingAs($this->{$property}, 'web')->get('/dashboard')->assertOk()
                ->assertDontSee('app/contractor-trip/list', false);
        }
    }
}
