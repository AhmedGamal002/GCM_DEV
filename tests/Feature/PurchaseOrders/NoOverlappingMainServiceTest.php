<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderStatusAction;
use App\Domain\PurchaseOrders\Rules\NoOverlappingMainServiceRule;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

/**
 * FRD §1.13's overlap rule with decision ب5, on the FRD's own example: the
 * Riyadh hotel's PO 1200, 1 Jan – 30 May 2026, solid waste transport, 90
 * trips / 9,000 SAR. "Today" is 1 March 2026.
 */
class NoOverlappingMainServiceTest extends PurchaseOrderTestCase
{
    private PurchaseOrder $po1200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->po1200 = app(CreatePurchaseOrderAction::class)->execute($this->payload(), null, $this->admin);
    }

    private function create(array $overrides = [], array $line = []): PurchaseOrder
    {
        return app(CreatePurchaseOrderAction::class)->execute($this->payload(['paper_number' => '1300', ...$overrides], $line), null, $this->admin);
    }

    private function assertRefusedOn(string $key, callable $write): void
    {
        try {
            $write();
            $this->fail('The overlapping purchase order was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $this->assertStringContainsString('ALN-1200', $e->errors()[$key][0]);
        }
    }

    public function test_the_same_service_in_the_same_project_cannot_overlap_even_by_one_day(): void
    {
        $this->assertRefusedOn('services.0.main_service_id', fn () => $this->create(['starts_on' => '2026-05-30', 'ends_on' => '2026-08-30']));
        $this->assertRefusedOn('services.0.main_service_id', fn () => $this->create(['starts_on' => '2025-12-01', 'ends_on' => '2026-01-01']));
        $this->assertRefusedOn('services.0.main_service_id', fn () => $this->create(['starts_on' => '2026-02-01', 'ends_on' => '2026-02-10']));

        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_the_day_after_it_ends_is_free(): void
    {
        $this->create(['starts_on' => '2026-05-31', 'ends_on' => '2026-12-31']);

        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_another_project_or_another_service_is_not_blocked(): void
    {
        $otherProject = Project::factory()->for($this->company)->create();
        $this->create(['project_id' => $otherProject->id]);

        $otherService = MainService::factory()->create();
        $this->create(['paper_number' => '1400'], ['main_service_id' => $otherService->id]);

        $this->assertSame(3, PurchaseOrder::count());
    }

    public function test_it_is_allowed_once_the_trips_of_the_service_ran_out(): void
    {
        $this->charge($this->po1200->services->first(), 90, 100);

        $this->create(['starts_on' => '2026-05-30', 'ends_on' => '2026-08-30']);

        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_it_is_allowed_once_the_cost_of_the_service_ran_out(): void
    {
        // 60 trips at 150 = 9,000 SAR: the cost is gone while 30 trips are still "left".
        $this->charge($this->po1200->services->first(), 60, 150);

        $this->create(['starts_on' => '2026-05-30', 'ends_on' => '2026-08-30']);

        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_it_is_still_blocked_while_the_service_is_only_about_to_run_out(): void
    {
        $this->charge($this->po1200->services->first(), 89, 100);

        $this->assertRefusedOn('services.0.main_service_id', fn () => $this->create(['starts_on' => '2026-05-30', 'ends_on' => '2026-08-30']));
    }

    public function test_it_is_allowed_once_the_other_po_has_ended(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 2));

        // A backdated PO overlapping 1200's last days: 1200 ended, its date ran out.
        $this->create(['starts_on' => '2026-05-15', 'ends_on' => '2026-08-30']);

        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_a_deactivated_po_never_blocks(): void
    {
        app(UpdatePurchaseOrderStatusAction::class)->execute($this->po1200, 'deactivated', $this->admin);

        $this->create();

        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_a_po_created_deactivated_is_not_checked_but_reactivating_it_is(): void
    {
        $second = $this->create(['operational_status' => 'deactivated']);

        try {
            app(UpdatePurchaseOrderStatusAction::class)->execute($second, 'active', $this->admin);
            $this->fail('Re-activating an overlapping PO was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
            $this->assertStringContainsString('ALN-1200', $e->errors()['status'][0]);
        }

        $this->assertSame('deactivated', $second->fresh()->operational_status);

        // Once 1200's service ran out, the re-activation goes through.
        $this->charge($this->po1200->services->first(), 90, 100);
        app(UpdatePurchaseOrderStatusAction::class)->execute($second, 'active', $this->admin);
        $this->assertSame('active', $second->fresh()->operational_status);
    }

    public function test_editing_the_dates_into_an_overlap_is_refused(): void
    {
        $later = $this->create(['starts_on' => '2026-06-01', 'ends_on' => '2026-12-31']);

        $this->assertRefusedOn('services.0.main_service_id', fn () => app(UpdatePurchaseOrderAction::class)->execute($later, ['starts_on' => '2026-05-20'], null, $this->admin));

        $this->assertSame('2026-06-01', $later->fresh()->starts_on->toDateString());
    }

    public function test_adding_or_switching_a_line_to_a_held_service_is_refused(): void
    {
        $other = MainService::factory()->create();
        $second = $this->create([], ['main_service_id' => $other->id]);
        $line = $second->services->first();

        // Adding solid waste transport as a second line.
        $this->assertRefusedOn('services.1.main_service_id', fn () => app(UpdatePurchaseOrderAction::class)->execute($second, ['services' => [
            ['id' => $line->id, 'main_service_id' => $other->id, 'quota_method' => 'trips_only', 'max_trips' => 10, 'trip_cost' => 10, 'alert_percent' => 20],
            ['main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 10, 'trip_cost' => 10, 'alert_percent' => 20],
        ]], null, $this->admin));

        // Switching the existing line to it.
        $this->assertRefusedOn('services.0.main_service_id', fn () => app(UpdatePurchaseOrderAction::class)->execute($second, ['services' => [
            ['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 10, 'trip_cost' => 10, 'alert_percent' => 20],
        ]], null, $this->admin));

        $this->assertSame([$other->id], $second->fresh()->services->pluck('main_service_id')->all());
    }

    public function test_a_quota_only_edit_does_not_run_the_check_again(): void
    {
        // An edit that touches neither the dates, the project nor the services can't create an overlap.
        $line = $this->po1200->services->first();

        app(UpdatePurchaseOrderAction::class)->execute($this->po1200, ['services' => [
            ['id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 120, 'trip_cost' => 100, 'alert_percent' => 25],
        ]], null, $this->admin);

        $this->assertSame(120, $line->fresh()->max_trips);
    }

    public function test_the_rule_lists_what_blocks_and_ignores_the_po_being_edited(): void
    {
        $conflicts = NoOverlappingMainServiceRule::conflicts($this->project->id, $this->service->id, '2026-03-01', '2026-03-31');
        $this->assertSame([$this->po1200->services->first()->id], $conflicts->modelKeys());

        $this->assertCount(0, NoOverlappingMainServiceRule::conflicts($this->project->id, $this->service->id, '2026-03-01', '2026-03-31', $this->po1200->id));
    }
}
