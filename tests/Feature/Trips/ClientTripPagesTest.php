<?php

namespace Tests\Feature\Trips;

use App\Models\Project;
use App\Models\Trip;
use App\Models\User;

/**
 * Part 7.5 — the client's trip pages (Blade shells): open to the client's
 * project manager / auditor only, a trip of a project they don't see is a
 * 404 (like the API), and the menu shows them "Trips".
 */
class ClientTripPagesTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_client_manager_opens_the_list_details_and_review_pages(): void
    {
        $trip = $this->createAwaiting();

        $this->actingAs($this->clientManager, 'web');
        $this->get('/app/client-trip/list')->assertOk()->assertSee('clientTripListTranslations', false);
        $this->get("/app/client-trip/view/{$trip->id}")->assertOk()->assertSee($trip->code);
        $this->get("/app/client-trip/review/{$trip->id}")->assertOk()->assertSee($trip->code);
    }

    public function test_the_details_page_has_the_trip_documents_card(): void
    {
        // Part 8.1 / د10: the client views the generated documents from its trip details — the shared card,
        // filled from GET /trips/{id} → documents (no combined-PDF button: that is GCM's).
        $trip = $this->createAwaiting();

        $this->actingAs($this->clientAuditor, 'web')
            ->get("/app/client-trip/view/{$trip->id}")
            ->assertOk()
            ->assertSee('id="ctv-generated-card"', false)
            ->assertSee('id="ctv-generated-hint"', false)
            ->assertSee(__('Trip documents'))
            ->assertDontSee('Export trip documents');
    }

    public function test_the_client_auditor_opens_the_pages_too(): void
    {
        $trip = $this->createAwaiting();

        $this->actingAs($this->clientAuditor, 'web');
        $this->get('/app/client-trip/list')->assertOk();
        $this->get("/app/client-trip/review/{$trip->id}")->assertOk();
    }

    public function test_another_projects_trip_is_a_404(): void
    {
        $other = Project::factory()->for($this->company)->create();
        $trip = Trip::factory()->forProject($other)->create();
        $client = User::factory()->client($this->company, 'client_project_manager', false)->create();
        $client->projects()->attach($this->project);

        $this->actingAs($client, 'web');
        $this->get("/app/client-trip/view/{$trip->id}")->assertNotFound();
        $this->get("/app/client-trip/review/{$trip->id}")->assertNotFound();
    }

    public function test_staff_have_their_own_pages(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/client-trip/list')->assertForbidden();
    }

    public function test_the_driver_has_no_client_pages(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/app/client-trip/list')->assertForbidden();
    }

    public function test_the_menu_shows_trips_to_the_client(): void
    {
        $this->actingAs($this->clientAuditor, 'web')->get('/app/client-trip/list')->assertOk()->assertSee('app/client-trip/list', false);
    }
}
