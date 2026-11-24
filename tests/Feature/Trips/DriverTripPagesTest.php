<?php

namespace Tests\Feature\Trips;

use App\Models\Driver;

/**
 * Part 7.6 — the driver's trip pages (Blade shells): his table, the create form, the details. A driver account
 * only; another driver's trip is a 404; staff and the client keep their own pages. One actor per test.
 */
class DriverTripPagesTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_driver_opens_his_list_form_and_details(): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->driver->user, 'web');

        $this->get('/app/driver-trip/list')->assertOk()->assertSee('driverTripListTranslations', false);
        $this->get('/app/driver-trip/add')->assertOk()->assertSee('driverTripAddConfig', false)->assertSee('id="driverTripForm"', false);
        $this->get("/app/driver-trip/view/{$trip->id}")->assertOk()->assertSee($trip->code)->assertSee('driverTripViewConfig', false);
    }

    public function test_the_create_form_has_no_gallery_and_no_typed_location(): void
    {
        $this->actingAs($this->driver->user, 'web');
        $html = $this->get('/app/driver-trip/add')->assertOk()->getContent();

        // the live photo and the location come only from the widgets (ج12): no file input for the photo, no location text box
        $this->assertStringNotContainsString('name="live_photo"', $html);
        $this->assertStringNotContainsString('name="location_url"', $html);
        $this->assertStringContainsString('id="df-take-photo"', $html);
        $this->assertStringContainsString('id="df-get-location"', $html);
        $this->assertMatchesRegularExpression('/id="df-submit"[^>]*disabled/', $html);
    }

    public function test_another_drivers_trip_is_a_404(): void
    {
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $theirs = $this->createAwaiting(['driver_id' => $other->id]);

        $this->actingAs($this->driver->user, 'web')->get("/app/driver-trip/view/{$theirs->id}")->assertNotFound();
    }

    public function test_an_unknown_trip_is_a_404(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/driver-trip/view/999999')->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('others')]
    public function test_nobody_but_a_driver_opens_them(string $property): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->{$property}, 'web');

        $this->get('/app/driver-trip/list')->assertForbidden();
        $this->get('/app/driver-trip/add')->assertForbidden();
        $this->get("/app/driver-trip/view/{$trip->id}")->assertForbidden();
    }

    public static function others(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client manager' => ['clientManager']];
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/driver-trip/list')->assertRedirect('/login');
    }

    public function test_the_driver_has_no_staff_or_client_trip_pages(): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->driver->user, 'web');

        $this->get('/app/trip/list')->assertForbidden();
        $this->get('/app/trip/add')->assertForbidden();
        $this->get("/app/trip/view/{$trip->id}")->assertForbidden();
        $this->get('/app/client-trip/list')->assertForbidden();
    }

    public function test_the_drivers_menu_has_trips_with_list_and_add_only(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/driver-trip/list')
            ->assertSee('app/driver-trip/list', false)
            ->assertSee('app/driver-trip/add', false)
            ->assertDontSee('app/trip/list', false)
            ->assertDontSee('app/client-trip/list', false);
    }

    public function test_the_other_roles_menus_do_not_show_the_drivers_entry(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/trip/list')->assertDontSee('app/driver-trip/list', false);
    }

    public function test_the_wording_is_in_both_languages(): void
    {
        $ar = json_decode(file_get_contents(lang_path('ar.json')), true);

        foreach (['My trips', 'On site', 'Upload', 'Current photo', 'Take the live location and the live photo to create the trip.'] as $key) {
            $this->assertArrayHasKey($key, $ar, "ar.json lacks: $key");
            $this->assertNotSame($key, $ar[$key], "ar.json has the English text for: $key");
        }
    }
    public function test_the_vehicle_type_is_plain_text_in_the_form_and_a_row_under_the_vehicle_in_the_details(): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->driver->user, 'web');

        $form = $this->get('/app/driver-trip/add')->assertOk()->getContent();
        $this->assertStringContainsString('id="df-vehicle_type"', $form);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="df-vehicle_type"/', $form);

        $details = $this->get("/app/driver-trip/view/{$trip->id}")->assertOk()->getContent();
        $this->assertLessThan(strpos($details, 'data-row="vehicle_type"'), strpos($details, 'data-row="vehicle"'));
        $this->assertLessThan(strpos($details, 'data-row="going"'), strpos($details, 'data-row="vehicle_type"'));
    }
    public function test_the_drivers_details_page_has_the_documents_card_and_no_export_and_the_receipt_takes_photos_only(): void
    {
        $trip = $this->createByDriver();

        $html = $this->actingAs($this->driver->user, 'web')->get("/app/driver-trip/view/{$trip->id}")->assertOk()->getContent();

        $this->assertStringContainsString('id="dv-generated-card"', $html);
        $this->assertStringNotContainsString('export-documents', $html);
        $this->assertStringContainsString('accept=".jpg,.jpeg,.png"', $html);
        $this->assertStringNotContainsString('.pdf', substr($html, strpos($html, 'dv-receipt-file') - 80, 260));
    }
}
