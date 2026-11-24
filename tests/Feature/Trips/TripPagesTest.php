<?php

namespace Tests\Feature\Trips;

use App\Models\Project;
use App\Models\Trip;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Part 7.4 — GCM's trip pages (Blade shells: list, add, details, edit). System Admin and Data Entry manage, the
 * Auditor views; the client has its own pages (7.5), the driver none yet. A trip a user can't see is a 404, like
 * on the API. One actor per test (the sanctum guard caches the first user).
 */
class TripPagesTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ── the list ────────────────────────────────────────────────────

    #[DataProvider('staffRoles')]
    public function test_staff_open_the_list(string $property, bool $canManage): void
    {
        $this->actingAs($this->{$property}, 'web');

        $this->get('/app/trip/list')
            ->assertOk()
            ->assertSee('tripListTranslations', false)
            ->assertSee('can_manage: '.($canManage ? 'true' : 'false'), false)
            ->assertSee('data-stat="cancelled"', false);
    }

    public static function staffRoles(): array
    {
        return [
            'system admin' => ['admin', true],
            'data entry' => ['dataEntry', true],
            'auditor' => ['auditor', false],
        ];
    }

    public function test_the_client_has_no_staff_list(): void
    {
        $this->actingAs($this->clientManager, 'web')->get('/app/trip/list')->assertForbidden();
    }

    public function test_the_driver_has_no_staff_list(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/trip/list')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/trip/list')->assertRedirect('/login');
    }

    // ── add / edit: managers only ───────────────────────────────────

    #[DataProvider('managers')]
    public function test_managers_open_the_add_and_edit_forms(string $property): void
    {
        $trip = $this->createAwaiting();
        $this->actingAs($this->{$property}, 'web');

        $this->get('/app/trip/add')->assertOk()->assertSee('tripFormConfig', false)->assertSee('id="tripForm"', false);
        $this->get("/app/trip/edit/{$trip->id}")->assertOk()->assertSee($trip->code)->assertSee('mode: "edit"', false);
    }

    public static function managers(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry']];
    }

    public function test_the_auditor_cannot_open_the_forms(): void
    {
        $trip = $this->createAwaiting();
        $this->actingAs($this->auditor, 'web');

        $this->get('/app/trip/add')->assertForbidden();
        $this->get("/app/trip/edit/{$trip->id}")->assertForbidden();
    }

    public function test_the_client_cannot_open_the_forms(): void
    {
        $trip = $this->createAwaiting();
        $this->actingAs($this->clientManager, 'web');

        $this->get('/app/trip/add')->assertForbidden();
        $this->get("/app/trip/edit/{$trip->id}")->assertForbidden();
    }

    // ── details ─────────────────────────────────────────────────────

    public function test_staff_open_the_details_of_a_trip(): void
    {
        $trip = $this->createAwaiting();

        $this->actingAs($this->auditor, 'web')
            ->get("/app/trip/view/{$trip->id}")
            ->assertOk()
            ->assertSee($trip->code)
            ->assertSee('tripViewConfig', false)
            ->assertSee('canManage: false', false);
    }

    public function test_a_manager_gets_the_manage_flag_on_the_details(): void
    {
        $trip = $this->createAwaiting();

        $this->actingAs($this->dataEntry, 'web')->get("/app/trip/view/{$trip->id}")->assertOk()->assertSee('canManage: true', false);
    }

    public function test_the_client_cannot_open_the_staff_details(): void
    {
        $trip = $this->createAwaiting();

        $this->actingAs($this->clientManager, 'web')->get("/app/trip/view/{$trip->id}")->assertForbidden();
    }

    public function test_an_unknown_trip_is_a_404(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get('/app/trip/view/999999')->assertNotFound();
        $this->get('/app/trip/edit/999999')->assertNotFound();
    }

    public function test_a_trip_of_another_tenant_is_a_404(): void
    {
        $trip = $this->createAwaiting();

        $other = \App\Models\Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $theirAdmin = User::factory()->create();
        $theirAdmin->assignRole('system_admin');

        $this->actingAs($theirAdmin, 'web')->get("/app/trip/view/{$trip->id}")->assertNotFound();
    }

    // ── the menu ────────────────────────────────────────────────────

    public function test_the_menu_shows_trips_with_add_for_managers_only(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/trip/list')
            ->assertSee('app/trip/list', false)
            ->assertSee('app/trip/add', false);
    }

    public function test_the_auditor_sees_the_trips_list_but_not_add_in_the_menu(): void
    {
        $this->actingAs($this->auditor, 'web')->get('/app/trip/list')
            ->assertSee('app/trip/list', false)
            ->assertDontSee('app/trip/add', false);
    }

    public function test_the_client_menu_keeps_its_own_trips_entry_only(): void
    {
        $this->actingAs($this->clientManager, 'web')->get('/app/client-trip/list')
            ->assertSee('app/client-trip/list', false)
            ->assertDontSee('app/trip/list', false)
            ->assertDontSee('app/trip/add', false);
    }

    // ── the wording the scripts read ────────────────────────────────

    public function test_the_forms_wording_is_in_both_languages(): void
    {
        $keys = [
            'Basic data', 'Approvals & services', 'Quantity, costs & facility', 'Signatures', 'Create a new trip', 'Add Trip',
            'Cancelled trips', 'Trip history', 'Remind the client to approve', 'Cancel the trip', 'Final approval of the trip',
            'Accept the client\'s objection', 'Reject the client\'s objection', 'Valid purchase orders',
        ];
        $ar = json_decode(file_get_contents(lang_path('ar.json')), true);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $ar, "ar.json lacks: $key");
            $this->assertNotSame($key, $ar[$key], "ar.json has the English text for: $key");
        }
    }

    public function test_the_client_is_turned_away_from_the_staff_pages_before_the_trip_is_looked_up(): void
    {
        $other = Project::factory()->for($this->company)->create();
        $trip = Trip::factory()->forProject($other)->create();

        // The staff pages never serve a client: 403 before the trip is even looked up.
        $this->actingAs($this->clientAuditor, 'web')->get("/app/trip/view/{$trip->id}")->assertForbidden();
    }
    // ── the vehicle type ────────────────────────────────────────────

    public function test_the_vehicle_type_is_plain_text_in_the_form_and_a_row_under_the_vehicle_in_the_details(): void
    {
        $trip = $this->createAwaiting();
        $this->actingAs($this->admin, 'web');

        // automatic fields are text, not read-only inputs that look editable
        $form = $this->get('/app/trip/add')->assertOk()->getContent();
        $this->assertStringContainsString('id="tf-vehicle_type"', $form);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="tf-(vehicle_type|vehicle_capacity|returning_capacity)"/', $form);

        // the details list the type right after the vehicle
        $details = $this->get("/app/trip/view/{$trip->id}")->assertOk()->getContent();
        $this->assertLessThan(strpos($details, 'data-row="vehicle_type"'), strpos($details, 'data-row="vehicle"'));
        $this->assertLessThan(strpos($details, 'data-row="going"'), strpos($details, 'data-row="vehicle_type"'));
    }
    public function test_the_details_page_has_the_documents_card_and_its_export_button(): void
    {
        $trip = $this->createAwaiting();

        $html = $this->actingAs($this->admin, 'web')->get("/app/trip/view/{$trip->id}")->assertOk()->getContent();

        $this->assertStringContainsString('id="tv-generated-card"', $html);
        $this->assertStringContainsString('id="tv-export-documents"', $html);
    }
}
