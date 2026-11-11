<?php

namespace Tests\Feature\PurchaseOrders\Api;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStates;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\PurchaseOrders\PurchaseOrderTestCase;

/**
 * Part 6.2 — the /api/v1/purchase-orders endpoints for the System Admin: create,
 * read, edit (with the lock of decision ب2), deactivate / re-activate, the five
 * stat cards and the attachment. Who else may call them is in
 * PurchaseOrderPolicyApiTest. "Today" is frozen at 2026-03-01 by the base class.
 */
class PurchaseOrderApiTest extends PurchaseOrderTestCase
{
    private function actAsAdmin(): static
    {
        return $this->actingAs($this->admin, 'web');
    }

    /** A PO of the default project through the API; returns its id. */
    private function createPo(array $overrides = [], array $line = []): int
    {
        return $this->actAsAdmin()
            ->postJson('/api/v1/purchase-orders', $this->payload($overrides, $line))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_creating_a_po_builds_the_number_from_the_company_prefix_and_returns_its_state(): void
    {
        $response = $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '0042']));

        $response->assertCreated()
            ->assertJsonPath('data.number', 'ALN-0042')
            ->assertJsonPath('data.paper_number', '0042')
            ->assertJsonPath('data.company.id', $this->company->id)
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.operational_status', 'active')
            ->assertJsonPath('data.currency', 'SAR')
            // pre-filled from the project's region (decision ب11)
            ->assertJsonPath('data.region', 'Riyadh')
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.timeline', 'running')
            ->assertJsonPath('data.services.0.max_trips', 90)
            // computed, never stored: 90 trips × 100 SAR
            ->assertJsonPath('data.services.0.max_trip_cost', 9000)
            ->assertJsonPath('data.services.0.max_ancillary_cost', null)
            ->assertJsonPath('data.totals.max_trips', 90)
            ->assertJsonPath('data.totals.trips_used', 0)
            ->assertJsonPath('data.totals.max_cost', 9000)
            ->assertJsonPath('data.editability.has_trips', false);

        $this->assertSame($this->admin->id, PurchaseOrder::firstOrFail()->updated_by);
    }

    public function test_a_trips_and_ancillary_line_returns_both_totals(): void
    {
        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(line: [
            'quota_method' => 'trips_and_ancillary',
            'max_trips' => 150,
            'trip_cost' => 50,
            'ancillary_cost_per_trip' => 30,
        ]))->assertCreated()
            ->assertJsonPath('data.services.0.max_trip_cost', 7500)
            ->assertJsonPath('data.services.0.ancillary_cost_per_trip', 30)
            ->assertJsonPath('data.services.0.max_ancillary_cost', 4500)
            ->assertJsonPath('data.totals.max_cost', 12000);
    }

    public function test_the_validation_errors_name_the_fields(): void
    {
        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '12A']))
            ->assertUnprocessable()->assertJsonValidationErrors('paper_number');

        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['ends_on' => '2025-12-31']))
            ->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(line: ['quota_method' => 'trips_and_ancillary']))
            ->assertUnprocessable()->assertJsonValidationErrors('services.0.ancillary_cost_per_trip');

        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['services' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('services');
    }

    public function test_a_taken_number_and_an_overlapping_service_are_refused_by_the_actions(): void
    {
        $this->createPo();

        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('paper_number');

        // another number, same project + service + overlapping dates (FRD example)
        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '1201', 'starts_on' => '2026-05-30', 'ends_on' => '2026-08-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('services.0.main_service_id');
    }

    public function test_the_additional_data_is_sanitized(): void
    {
        $response = $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload([
            'additional_data' => '<p>Notes</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        ]))->assertCreated();

        $html = $response->json('data.additional_data');
        $this->assertStringContainsString('Notes', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_an_empty_quill_editor_is_stored_as_nothing(): void
    {
        $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload(['additional_data' => '<p><br></p>']))
            ->assertCreated()->assertJsonPath('data.additional_data', null);
    }

    public function test_the_attachment_is_stored_privately_and_served_through_the_gate_checked_route(): void
    {
        Storage::fake('local');

        $response = $this->actAsAdmin()->post('/api/v1/purchase-orders', $this->payload([
            'attachment' => UploadedFile::fake()->create('po-1200.pdf', 200, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $id = $response->json('data.id');
        $path = PurchaseOrder::findOrFail($id)->attachment_path;
        $this->assertStringStartsWith("purchase-order-attachments/{$id}/", $path);
        Storage::disk('local')->assertExists($path);
        $response->assertJsonPath('data.attachment.extension', 'pdf');
        $this->assertStringContainsString("/api/v1/purchase-orders/{$id}/attachment", $response->json('data.attachment.download_url'));

        $this->actAsAdmin()->get("/api/v1/purchase-orders/{$id}/attachment")->assertOk();
    }

    public function test_the_attachment_must_be_a_pdf_or_an_image_up_to_5_mb(): void
    {
        Storage::fake('local');

        $this->actAsAdmin()->post('/api/v1/purchase-orders', $this->payload(['attachment' => UploadedFile::fake()->create('x.exe', 10)]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('attachment');

        $this->actAsAdmin()->post('/api/v1/purchase-orders', $this->payload(['attachment' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }

    public function test_a_po_without_an_attachment_has_a_404_download(): void
    {
        $id = $this->createPo();

        $this->actAsAdmin()->get("/api/v1/purchase-orders/{$id}/attachment")->assertNotFound();
    }

    public function test_show_returns_the_state_the_usage_and_the_edit_lock(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $this->charge($line, 3, 100);

        $this->actAsAdmin()->getJson("/api/v1/purchase-orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.totals.trips_used', 3)
            ->assertJsonPath('data.totals.cost_used', 300)
            ->assertJsonPath('data.services.0.components.trips.used', 3)
            ->assertJsonPath('data.services.0.components.trips.remaining', 87)
            ->assertJsonPath('data.editability.has_trips', true)
            ->assertJsonPath('data.editability.can_change_services', false)
            ->assertJsonPath('data.editability.services.0.id', $line->id)
            ->assertJsonPath('data.editability.services.0.min_max_trips', 3)
            ->assertJsonPath('data.editability.services.0.can_change', false);
    }

    public function test_the_state_labels_and_day_counts_follow_the_timeline(): void
    {
        $upcoming = $this->createPo(['paper_number' => '1', 'starts_on' => '2026-03-11', 'ends_on' => '2026-04-01']);
        $service2 = MainService::factory()->create(['name' => 'Other service']);
        $ended = $this->createPo(['paper_number' => '2', 'starts_on' => '2025-01-01', 'ends_on' => '2025-02-01'], ['main_service_id' => $service2->id]);

        $this->actAsAdmin()->getJson("/api/v1/purchase-orders/{$upcoming}")
            ->assertJsonPath('data.timeline', 'not_started')
            ->assertJsonPath('data.days_until_start', 10)
            ->assertJsonPath('data.days_remaining', null);

        $this->actAsAdmin()->getJson("/api/v1/purchase-orders/{$ended}")
            ->assertJsonPath('data.timeline', 'ended')
            ->assertJsonPath('data.days_since_end', 393)
            ->assertJsonPath('data.status', 'exhausted');
    }

    public function test_an_unknown_id_is_a_404(): void
    {
        $this->actAsAdmin()->getJson('/api/v1/purchase-orders/999999')->assertNotFound();
        $this->actAsAdmin()->patchJson('/api/v1/purchase-orders/999999', [])->assertNotFound();
        $this->actAsAdmin()->patchJson('/api/v1/purchase-orders/999999/status', ['status' => 'active'])->assertNotFound();
    }

    // ── edit: the lock of decision ب2 ────────────────────────────────

    public function test_without_trips_everything_can_be_edited(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", [
            'paper_number' => '1300',
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-06-30',
            'region' => 'Jeddah',
            'services' => [['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 120, 'trip_cost' => 80, 'alert_percent' => 10]],
        ])->assertOk()
            ->assertJsonPath('data.number', 'ALN-1300')
            ->assertJsonPath('data.ends_on', '2026-06-30')
            ->assertJsonPath('data.region', 'Jeddah')
            ->assertJsonPath('data.services.0.max_trips', 120)
            ->assertJsonPath('data.services.0.max_trip_cost', 9600);
    }

    public function test_with_a_trip_only_the_quota_can_change(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $this->charge($line, 5, 100);

        // the dates lock with the first trip
        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", ['ends_on' => '2026-12-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        // the form sends everything: a field with its current value is not a change
        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", [
            'paper_number' => '1200',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-05-30',
            'services' => [['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 200, 'trip_cost' => 120, 'alert_percent' => 15]],
        ])->assertOk()
            ->assertJsonPath('data.services.0.max_trips', 200)
            ->assertJsonPath('data.services.0.trip_cost', 120)
            ->assertJsonPath('data.services.0.alert_percent', 15);
    }

    public function test_the_max_trips_cannot_go_below_what_was_consumed(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $this->charge($line, 5, 100);

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", [
            'services' => [['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 4, 'trip_cost' => 100, 'alert_percent' => 20]],
        ])->assertUnprocessable()->assertJsonValidationErrors('services.0.max_trips');
    }

    public function test_a_trip_blocks_adding_a_line_and_replacing_the_attachment(): void
    {
        Storage::fake('local');
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $this->charge($line, 1, 100);
        $other = MainService::factory()->create();

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", [
            'services' => [
                ['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 90, 'trip_cost' => 100, 'alert_percent' => 20],
                ['main_service_id' => $other->id, 'quota_method' => 'trips_only', 'max_trips' => 10, 'trip_cost' => 10, 'alert_percent' => 20],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('services.1');

        $this->actAsAdmin()->post("/api/v1/purchase-orders/{$id}", [
            '_method' => 'PATCH',
            'attachment' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }

    public function test_a_cancelled_trip_lifts_the_lock(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $tripId = $this->charge($line, 1, 100)[0];
        app(ReleaseQuotaAction::class)->execute($tripId, $this->admin);

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}", ['ends_on' => '2026-12-31'])
            ->assertOk()->assertJsonPath('data.ends_on', '2026-12-31');
    }

    // ── status ──────────────────────────────────────────────────────

    public function test_deactivating_and_reactivating_work_with_or_without_trips(): void
    {
        $id = $this->createPo();
        $line = PurchaseOrderService::where('purchase_order_id', $id)->firstOrFail();
        $this->charge($line, 2, 100);

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}/status", ['status' => 'deactivated'])
            ->assertOk()->assertJsonPath('data.operational_status', 'deactivated');

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}/status", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.operational_status', 'active');
    }

    public function test_the_status_must_be_active_or_deactivated(): void
    {
        $id = $this->createPo();

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$id}/status", ['status' => 'inactive'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_reactivating_is_refused_when_another_po_now_holds_the_service(): void
    {
        $first = $this->createPo();
        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$first}/status", ['status' => 'deactivated'])->assertOk();
        // the deactivated PO doesn't block a new one over the same days
        $this->createPo(['paper_number' => '1201']);

        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$first}/status", ['status' => 'active'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    // ── list ────────────────────────────────────────────────────────

    public function test_the_list_is_paginated_and_carries_each_pos_state(): void
    {
        $this->createPo();

        $this->actAsAdmin()->getJson('/api/v1/purchase-orders?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('data.0.number', 'ALN-1200')
            ->assertJsonPath('data.0.status', 'valid')
            ->assertJsonPath('data.0.days_remaining', 91)
            ->assertJsonPath('data.0.company.name', 'Al Nakheel')
            ->assertJsonPath('data.0.project.name', 'Riyadh Hotel');
    }

    public function test_the_list_filters_by_company_project_account_status_and_search(): void
    {
        $this->createPo();

        $beta = Company::factory()->create(['prefix' => 'BET', 'name' => 'Beta Gulf']);
        $betaProject = Project::factory()->for($beta)->create(['name' => 'Beta Plant']);
        $betaService = MainService::factory()->create();
        $betaId = $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload([
            'paper_number' => '77', 'company_id' => $beta->id, 'project_id' => $betaProject->id,
        ], ['main_service_id' => $betaService->id]))->assertCreated()->json('data.id');
        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$betaId}/status", ['status' => 'deactivated'])->assertOk();

        $numbers = fn (string $query) => collect($this->actAsAdmin()->getJson("/api/v1/purchase-orders?{$query}")->assertOk()->json('data'))
            ->pluck('number')->sort()->values()->all();

        $this->assertSame(['ALN-1200', 'BET-77'], $numbers(''));
        $this->assertSame(['BET-77'], $numbers("company_id={$beta->id}"));
        $this->assertSame(['ALN-1200'], $numbers("project_id={$this->project->id}"));
        $this->assertSame(['BET-77'], $numbers('operational_status=deactivated'));
        $this->assertSame(['BET-77'], $numbers('search=Beta'));
        $this->assertSame(['ALN-1200'], $numbers('search=Riyadh'));
        $this->assertSame(['ALN-1200'], $numbers('search=1200'));
        $this->assertSame(['ALN-1200'], $numbers("company_id={$this->company->id}&search=ALN"));
    }

    public function test_the_time_range_filter_matches_pos_whose_dates_overlap_it(): void
    {
        $this->createPo(); // 2026-01-01 → 2026-05-30
        $other = MainService::factory()->create();
        $this->createPo(['paper_number' => '2', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31'], ['main_service_id' => $other->id]);

        $numbers = fn (string $query) => collect($this->actAsAdmin()->getJson("/api/v1/purchase-orders?{$query}")->json('data'))->pluck('number')->sort()->values()->all();

        $this->assertSame(['ALN-1200'], $numbers('date_from=2026-05-30&date_to=2026-06-30'));
        $this->assertSame(['ALN-1200', 'ALN-2'], $numbers('date_from=2026-05-01&date_to=2026-10-01'));
        $this->assertSame([], $numbers('date_from=2026-06-01&date_to=2026-08-31'));
        $this->assertSame(['ALN-2'], $numbers('date_from=2026-12-31'));
    }

    public function test_the_items_status_filter_is_resolved_from_the_ledger(): void
    {
        $this->createPo();
        $other = MainService::factory()->create();
        $near = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '2'], ['main_service_id' => $other->id, 'max_trips' => 10]))->firstOrFail();
        $third = MainService::factory()->create();
        $gone = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '3'], ['main_service_id' => $third->id, 'max_trips' => 10]))->firstOrFail();

        $this->charge($near, 9, 100);   // 1 of 10 left = 10% < the 20% alert
        $this->charge($gone, 10, 100);  // every trip used

        $numbers = fn (string $status) => collect($this->actAsAdmin()->getJson("/api/v1/purchase-orders?status={$status}")->assertOk()->json('data'))->pluck('number')->sort()->values()->all();

        $this->assertSame(['ALN-1200'], $numbers('valid'));
        $this->assertSame(['ALN-2'], $numbers('near_exhaustion'));
        $this->assertSame(['ALN-3'], $numbers('exhausted'));
        // an unknown value is ignored like an empty filter
        $this->assertCount(3, $this->actAsAdmin()->getJson('/api/v1/purchase-orders?status=bogus')->json('data'));
    }

    public function test_the_list_sorts_only_by_the_allowed_columns(): void
    {
        $this->createPo();
        $other = MainService::factory()->create();
        $this->createPo(['paper_number' => '5'], ['main_service_id' => $other->id]);

        $order = fn (string $query) => collect($this->actAsAdmin()->getJson("/api/v1/purchase-orders?{$query}")->assertOk()->json('data'))->pluck('number')->all();

        $this->assertSame(['ALN-1200', 'ALN-5'], $order('sort_by=number&sort_dir=asc'));
        $this->assertSame(['ALN-5', 'ALN-1200'], $order('sort_by=number&sort_dir=desc'));
        // a column outside the allowlist falls back to the default instead of reaching the SQL
        $this->actAsAdmin()->getJson('/api/v1/purchase-orders?sort_by=updated_by;drop')->assertOk();
    }

    public function test_the_list_sorts_and_pages_by_the_items_status_in_the_database(): void
    {
        $this->createPo(['paper_number' => '1']);                                  // valid
        $nearService = MainService::factory()->create();
        $near = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '2'], ['main_service_id' => $nearService->id, 'max_trips' => 10]))->firstOrFail();
        $goneService = MainService::factory()->create();
        $gone = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '3'], ['main_service_id' => $goneService->id, 'max_trips' => 10]))->firstOrFail();
        $this->charge($near, 9, 100);
        $this->charge($gone, 10, 100);

        $order = fn (string $query) => collect($this->actAsAdmin()->getJson("/api/v1/purchase-orders?{$query}")->assertOk()->json('data'))->pluck('number')->all();

        $this->assertSame(['ALN-1', 'ALN-2', 'ALN-3'], $order('sort_by=status&sort_dir=asc'));
        $this->assertSame(['ALN-3', 'ALN-2', 'ALN-1'], $order('sort_by=status&sort_dir=desc'));

        // filtering and paging are done by the database, so the totals are right
        $page = $this->actAsAdmin()->getJson('/api/v1/purchase-orders?status=near_exhaustion,bogus')->assertOk();
        $this->assertSame(3, $page->json('meta.total'), 'an unknown status is ignored');

        $this->actAsAdmin()->getJson('/api/v1/purchase-orders?status=exhausted&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.number', 'ALN-3');
    }
    // ── stats ───────────────────────────────────────────────────────

    public function test_the_stats_count_active_pos_only(): void
    {
        $line = PurchaseOrderService::where('purchase_order_id', $this->createPo(line: ['quota_method' => 'trips_and_ancillary', 'max_trips' => 150, 'trip_cost' => 50, 'ancillary_cost_per_trip' => 30]))->firstOrFail();
        $this->charge($line, 4, 50, 30);

        $beta = Company::factory()->create(['prefix' => 'BET']);
        $betaProject = Project::factory()->for($beta)->create();
        $betaService = MainService::factory()->create();
        $betaId = $this->actAsAdmin()->postJson('/api/v1/purchase-orders', $this->payload([
            'paper_number' => '9', 'company_id' => $beta->id, 'project_id' => $betaProject->id,
        ], ['main_service_id' => $betaService->id, 'max_trips' => 10, 'trip_cost' => 10]))->json('data.id');
        $this->actAsAdmin()->patchJson("/api/v1/purchase-orders/{$betaId}/status", ['status' => 'deactivated'])->assertOk();

        $this->actAsAdmin()->getJson('/api/v1/purchase-orders/stats')
            ->assertOk()
            ->assertJsonPath('data.purchase_orders', 1)
            ->assertJsonPath('data.companies', 1)
            ->assertJsonPath('data.projects', 1)
            ->assertJsonPath('data.trips', 4)
            // 150 × 50 + 150 × 30
            ->assertJsonPath('data.trip_cost_total', 7500)
            ->assertJsonPath('data.ancillary_cost_total', 4500)
            ->assertJsonPath('data.cost_items_total', 12000);
    }

    public function test_a_cancelled_trip_leaves_the_trip_count(): void
    {
        $line = PurchaseOrderService::where('purchase_order_id', $this->createPo())->firstOrFail();
        $ids = $this->charge($line, 3, 100);
        app(ReleaseQuotaAction::class)->execute($ids[0], $this->admin);

        $this->actAsAdmin()->getJson('/api/v1/purchase-orders/stats')->assertJsonPath('data.trips', 2);
    }

    // ── the batch states equal the one-by-one describe() ────────────

    public function test_the_batched_states_equal_describe_for_every_timeline_and_status(): void
    {
        $orders = [];
        $orders[] = PurchaseOrderService::where('purchase_order_id', $this->createPo())->firstOrFail();
        $near = MainService::factory()->create();
        $orders[] = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '2'], ['main_service_id' => $near->id, 'max_trips' => 10]))->firstOrFail();
        $this->charge($orders[1], 9, 100);
        $future = MainService::factory()->create();
        $this->createPo(['paper_number' => '3', 'starts_on' => '2026-08-01', 'ends_on' => '2026-09-01'], ['main_service_id' => $future->id]);
        $past = MainService::factory()->create();
        $this->createPo(['paper_number' => '4', 'starts_on' => '2025-01-01', 'ends_on' => '2025-02-01'], ['main_service_id' => $past->id]);

        $pos = PurchaseOrder::with('services')->get();
        $batched = PurchaseOrderStates::forMany($pos);

        $this->assertCount(4, $batched);
        foreach ($pos as $po) {
            $this->assertEquals(PurchaseOrderStatus::describe($po->fresh('services')), $batched[$po->id], "PO {$po->number}");
        }
    }

    public function test_the_list_runs_a_fixed_number_of_queries_however_many_pos_there_are(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createPo(['paper_number' => (string) $i], ['main_service_id' => MainService::factory()->create()->id]);
        }

        DB::enableQueryLog();
        $this->actAsAdmin()->getJson('/api/v1/purchase-orders')->assertOk();
        $three = count(DB::getQueryLog());
        DB::flushQueryLog();

        for ($i = 4; $i <= 9; $i++) {
            $this->createPo(['paper_number' => (string) $i], ['main_service_id' => MainService::factory()->create()->id]);
        }
        DB::flushQueryLog();
        $this->actAsAdmin()->getJson('/api/v1/purchase-orders')->assertOk();
        $nine = count(DB::getQueryLog());

        $this->assertSame($three, $nine, 'the list must not run a query per PO');
    }
}
