<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderStatusAction;
use App\Domain\PurchaseOrders\Quota\DeductQuotaAction;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Domain\PurchaseOrders\Support\PurchaseOrderEditability;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\QuotaLedgerEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Decision ب2 (2026-11-09): no trip on the PO = everything can be edited;
 * a trip on it = the quota only (the dates lock too), max trips never below
 * what was consumed. And ب3: a new trip cost only affects later trips.
 */
class PurchaseOrderEditabilityTest extends PurchaseOrderTestCase
{
    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->po = app(CreatePurchaseOrderAction::class)->execute($this->payload(['region' => 'Riyadh', 'additional_data' => '<p>Notes</p>']), null, $this->admin);
    }

    private function lineInput(array $overrides = []): array
    {
        $line = $this->po->services->first();

        return [
            'id' => $line->id,
            'main_service_id' => $line->main_service_id,
            'quota_method' => $line->quota_method,
            'max_trips' => $line->max_trips,
            'trip_cost' => (float) $line->trip_cost,
            'alert_percent' => $line->alert_percent,
            ...$overrides,
        ];
    }

    private function update(array $data, ?UploadedFile $file = null): PurchaseOrder
    {
        return app(UpdatePurchaseOrderAction::class)->execute($this->po, $data, $file, $this->admin);
    }

    private function assertRefused(string $key, array $data, ?UploadedFile $file = null): void
    {
        try {
            $this->update($data, $file);
            $this->fail("The change to [{$key}] was accepted.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    // ------------------------------------------------------------ no trip yet

    public function test_without_trips_everything_is_editable(): void
    {
        $editability = PurchaseOrderEditability::for($this->po);

        $this->assertFalse($editability->hasTrips());
        $this->assertSame(PurchaseOrderEditability::ORDER_FIELDS, $editability->editableFields());
        $this->assertTrue($editability->canChangeServices());
        $this->assertTrue($editability->canEdit('starts_on'));
    }

    public function test_without_trips_the_number_company_project_dates_and_notes_change(): void
    {
        $company = Company::factory()->create(['prefix' => 'BRJ']);
        $project = Project::factory()->for($company)->create();

        $po = $this->update([
            'paper_number' => '0042',
            'company_id' => $company->id,
            'project_id' => $project->id,
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-07-31',
            'region' => 'Jeddah',
            'additional_data' => '<p>New</p>',
        ], UploadedFile::fake()->create('po.pdf', 100, 'application/pdf'));

        $this->assertSame('BRJ-0042', $po->number);
        $this->assertSame($company->id, $po->company_id);
        $this->assertSame($project->id, $po->project_id);
        $this->assertSame('2026-07-31', $po->ends_on->toDateString());
        $this->assertSame('Jeddah', $po->region);
        $this->assertSame('<p>New</p>', $po->additional_data);
        Storage::disk('local')->assertExists($po->attachment_path);
    }

    public function test_without_trips_lines_can_be_added_removed_and_changed(): void
    {
        $hazardous = MainService::factory()->create();
        $supply = MainService::factory()->create();

        $po = $this->update(['services' => [
            $this->lineInput(['main_service_id' => $hazardous->id, 'quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 30]),
            ['main_service_id' => $supply->id, 'quota_method' => 'trips_only', 'max_trips' => 5, 'trip_cost' => 10, 'alert_percent' => 10],
        ]]);

        $this->assertSame([$hazardous->id, $supply->id], $po->services->pluck('main_service_id')->all());
        $this->assertSame('30.00', $po->services[0]->ancillary_cost_per_trip);

        // ...and a line left out of the list is removed.
        $po = $this->update(['services' => [$this->lineInput(['main_service_id' => $hazardous->id, 'quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 30])]]);
        $this->assertSame([$hazardous->id], $po->services->pluck('main_service_id')->all());
    }

    public function test_a_line_removed_and_added_again_for_the_same_service_is_fine(): void
    {
        $po = $this->update(['services' => [
            ['main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 10, 'trip_cost' => 10, 'alert_percent' => 10],
        ]]);

        $this->assertCount(1, $po->services);
        $this->assertSame(10, $po->services->first()->max_trips);
    }

    // ------------------------------------------------------------ a trip on it

    /** @return array<string, array{string, mixed}> */
    public static function lockedOrderFields(): array
    {
        return [
            'paper number' => ['paper_number', '9999'],
            'company' => ['company_id', 'other-company'],
            'project' => ['project_id', 'other-project'],
            'start date' => ['starts_on', '2026-01-02'],
            'end date' => ['ends_on', '2026-06-30'],
            'region' => ['region', 'Jeddah'],
            'notes' => ['additional_data', '<p>Changed</p>'],
        ];
    }

    /** @dataProvider lockedOrderFields */
    public function test_with_a_trip_the_pos_own_fields_are_locked(string $field, mixed $value): void
    {
        $this->charge($this->po->services->first());

        if ($value === 'other-company') {
            $value = Company::factory()->create()->id;
        } elseif ($value === 'other-project') {
            $value = Project::factory()->for($this->company)->create()->id;
        }

        $this->assertRefused($field, [$field => $value]);
    }

    public function test_with_a_trip_the_attachment_is_locked_too(): void
    {
        $this->charge($this->po->services->first());

        $this->assertRefused('attachment', [], UploadedFile::fake()->create('po.pdf', 100, 'application/pdf'));
    }

    public function test_with_a_trip_sending_the_current_values_back_is_not_a_change(): void
    {
        $this->charge($this->po->services->first());

        // The edit form sends every field: unchanged values must go through.
        $po = $this->update([
            'paper_number' => '1200',
            'company_id' => $this->company->id,
            'project_id' => (string) $this->project->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-05-30',
            'region' => 'Riyadh',
            'additional_data' => '<p>Notes</p>',
            'services' => [$this->lineInput(['max_trips' => 95])],
        ]);

        $this->assertSame(95, $po->services->first()->max_trips);
    }

    public function test_with_a_trip_the_quota_of_each_line_stays_editable(): void
    {
        $this->charge($this->po->services->first());

        $po = $this->update(['services' => [$this->lineInput(['max_trips' => 120, 'trip_cost' => 80, 'alert_percent' => 30])]]);

        $line = $po->services->first();
        $this->assertSame([120, '80.00', 30], [$line->max_trips, $line->trip_cost, $line->alert_percent]);
        $this->assertTrue(PurchaseOrderEditability::for($po)->hasTrips());
        $this->assertSame([], PurchaseOrderEditability::for($po)->editableFields());
    }

    public function test_with_a_trip_max_trips_cannot_go_below_what_was_consumed(): void
    {
        $this->charge($this->po->services->first(), 4);

        $this->assertRefused('services.0.max_trips', ['services' => [$this->lineInput(['max_trips' => 3])]]);

        $po = $this->update(['services' => [$this->lineInput(['max_trips' => 4])]]);
        $this->assertSame(4, $po->services->first()->max_trips);
    }

    public function test_with_a_trip_no_line_can_be_added_removed_or_switched(): void
    {
        $this->charge($this->po->services->first());
        $other = MainService::factory()->create();

        $this->assertRefused('services.1', ['services' => [
            $this->lineInput(),
            ['main_service_id' => $other->id, 'quota_method' => 'trips_only', 'max_trips' => 5, 'trip_cost' => 10, 'alert_percent' => 10],
        ]]);
        $this->assertRefused('services', ['services' => [
            ['main_service_id' => $other->id, 'quota_method' => 'trips_only', 'max_trips' => 5, 'trip_cost' => 10, 'alert_percent' => 10],
        ]]);
        $this->assertRefused('services.0.main_service_id', ['services' => [$this->lineInput(['main_service_id' => $other->id])]]);
        $this->assertRefused('services.0.quota_method', ['services' => [$this->lineInput(['quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 5])]]);

        $this->assertSame([$this->service->id], $this->po->fresh()->services->pluck('main_service_id')->all());
    }

    public function test_a_line_of_another_po_cannot_be_edited_through_this_one(): void
    {
        $foreign = $this->line([], '2026-06-01', '2026-12-31');

        $this->assertRefused('services.0.id', ['services' => [['id' => $foreign->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 1, 'trip_cost' => 1, 'alert_percent' => 1]]]);
    }

    public function test_deactivating_and_reactivating_work_with_trips(): void
    {
        $this->charge($this->po->services->first());

        app(UpdatePurchaseOrderStatusAction::class)->execute($this->po, 'deactivated', $this->admin);
        $this->assertSame('deactivated', $this->po->fresh()->operational_status);

        app(UpdatePurchaseOrderStatusAction::class)->execute($this->po, 'active', $this->admin);
        $this->assertSame('active', $this->po->fresh()->operational_status);
    }

    // ------------------------------------------------------------ cancelled trips

    public function test_a_cancelled_trip_lifts_the_lock_but_its_line_keeps_its_service(): void
    {
        [$trip] = $this->charge($this->po->services->first());
        app(ReleaseQuotaAction::class)->execute($trip, $this->admin);

        $editability = PurchaseOrderEditability::for($this->po);
        $this->assertFalse($editability->hasTrips());
        $this->assertFalse($editability->canChangeLine($this->po->services->first()));

        $po = $this->update(['ends_on' => '2026-06-15']);
        $this->assertSame('2026-06-15', $po->ends_on->toDateString());

        $this->assertRefused('services.0.main_service_id', ['services' => [$this->lineInput(['main_service_id' => MainService::factory()->create()->id])]]);
        $this->assertRefused('services', ['services' => [
            ['main_service_id' => MainService::factory()->create()->id, 'quota_method' => 'trips_only', 'max_trips' => 5, 'trip_cost' => 10, 'alert_percent' => 10],
        ]]);
    }

    // ------------------------------------------------------------ ب3

    public function test_a_new_trip_cost_only_affects_the_trips_recorded_after_it(): void
    {
        $line = $this->po->services->first();
        app(DeductQuotaAction::class)->execute($line, 1, 100, 0, $this->admin);

        $this->update(['services' => [$this->lineInput(['trip_cost' => 120])]]);

        // The trip action (7.1) reads the cost from the line when it records a trip.
        $line->refresh();
        app(DeductQuotaAction::class)->execute($line, 2, (float) $line->trip_cost, 0, $this->admin);

        $this->assertSame(['100.00', '120.00'], QuotaLedgerEntry::orderBy('id')->pluck('trip_cost_delta')->all());
        $this->assertSame(220.0, QuotaBalances::for($line)->tripCostUsed);
        $this->assertSame(90 * 120.0, QuotaBalances::for($line)->tripCostMax);
    }

    public function test_to_array_tells_the_form_what_it_may_send(): void
    {
        $this->charge($line = $this->po->services->first(), 3);

        $this->assertSame([
            'has_trips' => true,
            'editable_fields' => [],
            'can_change_services' => false,
            'services' => [$line->id => [
                'can_change' => false,
                'min_max_trips' => 3,
                'editable_fields' => PurchaseOrderEditability::QUOTA_FIELDS,
            ]],
        ], PurchaseOrderEditability::for($this->po)->toArray());
    }
}
