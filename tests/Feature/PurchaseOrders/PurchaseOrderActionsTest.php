<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderStatusAction;
use App\Domain\PurchaseOrders\Support\PurchaseOrderRules;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SubService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creating / editing a PO keeps it consistent (FRD §1.13 + decisions ب4,
 * ب11, أ4), and a main service in an active PO is "in use" (ج5).
 */
class PurchaseOrderActionsTest extends PurchaseOrderTestCase
{
    private function create(array $overrides = [], array $line = [], ?UploadedFile $file = null): PurchaseOrder
    {
        return app(CreatePurchaseOrderAction::class)->execute($this->payload($overrides, $line), $file, $this->admin);
    }

    private function assertRefused(string $key, callable $write): void
    {
        try {
            $write();
            $this->fail("[{$key}] was accepted.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    // ------------------------------------------------------------ create

    public function test_it_creates_a_po_with_its_lines(): void
    {
        Storage::fake('local');

        $po = $this->create(['additional_data' => '<p>Signed copy</p>'], [], UploadedFile::fake()->create('po.pdf', 200, 'application/pdf'));

        $this->assertSame('ALN-1200', $po->number);
        $this->assertSame('1200', $po->paperNumber());
        $this->assertSame($this->tenant->id, $po->tenant_id);
        $this->assertSame($this->company->id, $po->company_id);
        $this->assertSame($this->project->id, $po->project_id);
        $this->assertSame('active', $po->operational_status);
        $this->assertSame('<p>Signed copy</p>', $po->additional_data);
        $this->assertSame($this->admin->id, $po->updated_by);

        $line = $po->services->sole();
        $this->assertSame([$this->service->id, 'trips_only', 90, '100.00', '0.00', 20], [
            $line->main_service_id, $line->quota_method, $line->max_trips, $line->trip_cost, $line->ancillary_cost_per_trip, $line->alert_percent,
        ]);
        $this->assertSame(9000.0, $line->maxTripCost());

        Storage::disk('local')->assertExists($po->attachment_path);
        $this->assertStringStartsWith("purchase-order-attachments/{$po->id}/", $po->attachment_path);
    }

    public function test_the_region_is_prefilled_from_the_project_unless_given(): void
    {
        $this->assertSame('Riyadh', $this->create()->region);
        $this->assertSame('Dammam', $this->create(['paper_number' => '2', 'region' => 'Dammam', 'starts_on' => '2027-01-01', 'ends_on' => '2027-02-01'])->region);
        $this->assertNull($this->create(['paper_number' => '3', 'region' => null, 'starts_on' => '2028-01-01', 'ends_on' => '2028-02-01'])->region);
    }

    public function test_a_trips_only_line_drops_any_ancillary_cost_and_the_other_method_keeps_it(): void
    {
        $po = $this->create([], ['ancillary_cost_per_trip' => 30]);
        $this->assertSame('0.00', $po->services->first()->ancillary_cost_per_trip);
        $this->assertSame(0.0, $po->services->first()->maxAncillaryCost());

        $po = $this->create(['paper_number' => '1201', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-01'], ['quota_method' => 'trips_and_ancillary', 'max_trips' => 150, 'trip_cost' => 50, 'ancillary_cost_per_trip' => 30]);
        $this->assertSame(7500.0, $po->services->first()->maxTripCost());
        $this->assertSame(4500.0, $po->services->first()->maxAncillaryCost());
    }

    public function test_the_notes_are_sanitised(): void
    {
        $po = $this->create(['additional_data' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('script', (string) $po->fresh()->additional_data);
        $this->assertStringNotContainsString('onerror', (string) $po->fresh()->additional_data);
    }

    // ------------------------------------------------------------ the number

    public function test_the_paper_number_keeps_its_leading_zeros(): void
    {
        $this->assertSame('ALN-0012', $this->create(['paper_number' => '0012'])->number);
    }

    public function test_the_paper_number_is_digits_only_up_to_ten(): void
    {
        $this->assertSame('ALN-1234567890', $this->create(['paper_number' => '1234567890'])->number);

        foreach (['12345678901', '12A4', '-12', '1 2', ''] as $bad) {
            $this->assertRefused('paper_number', fn () => $this->create(['paper_number' => $bad, 'starts_on' => '2030-01-01', 'ends_on' => '2030-02-01']));
        }
    }

    public function test_a_number_is_never_given_out_twice_even_once_its_po_is_deactivated(): void
    {
        $first = $this->create();
        app(UpdatePurchaseOrderStatusAction::class)->execute($first, 'deactivated', $this->admin);

        $this->assertRefused('paper_number', fn () => $this->create(['starts_on' => '2027-01-01', 'ends_on' => '2027-02-01']));
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_the_same_paper_number_under_another_company_is_another_number(): void
    {
        $this->create();
        $other = Company::factory()->create(['prefix' => 'BRJ']);
        $project = Project::factory()->for($other)->create();

        $po = $this->create(['company_id' => $other->id, 'project_id' => $project->id]);

        $this->assertSame('BRJ-1200', $po->number);
    }

    public function test_changing_the_company_rebuilds_the_number_and_needs_a_project_of_it(): void
    {
        $po = $this->create();
        $other = Company::factory()->create(['prefix' => 'BRJ']);
        $project = Project::factory()->for($other)->create();

        $this->assertRefused('project_id', fn () => app(UpdatePurchaseOrderAction::class)->execute($po, ['company_id' => $other->id], null, $this->admin));

        $po = app(UpdatePurchaseOrderAction::class)->execute($po, ['company_id' => $other->id, 'project_id' => $project->id], null, $this->admin);
        $this->assertSame('BRJ-1200', $po->number);
    }

    public function test_editing_the_number_into_one_already_used_is_refused(): void
    {
        $this->create();
        $second = $this->create(['paper_number' => '1300', 'starts_on' => '2027-01-01', 'ends_on' => '2027-02-01']);

        $this->assertRefused('paper_number', fn () => app(UpdatePurchaseOrderAction::class)->execute($second, ['paper_number' => '1200'], null, $this->admin));
    }

    // ------------------------------------------------------------ consistency

    public function test_the_project_must_belong_to_the_company(): void
    {
        $foreignProject = Project::factory()->create();

        $this->assertRefused('project_id', fn () => $this->create(['project_id' => $foreignProject->id]));
    }

    public function test_only_an_active_company_and_project_get_a_new_po(): void
    {
        $inactiveCompany = Company::factory()->deactivated()->create();
        $project = Project::factory()->for($inactiveCompany)->create();
        $this->assertRefused('company_id', fn () => $this->create(['company_id' => $inactiveCompany->id, 'project_id' => $project->id]));

        $inactiveProject = Project::factory()->for($this->company)->deactivated()->create();
        $this->assertRefused('project_id', fn () => $this->create(['project_id' => $inactiveProject->id]));
    }

    public function test_a_company_deactivated_later_does_not_block_editing_its_po(): void
    {
        $po = $this->create();
        $this->company->forceFill(['operational_status' => 'deactivated'])->save();

        $po = app(UpdatePurchaseOrderAction::class)->execute($po, ['region' => 'Qassim'], null, $this->admin);

        $this->assertSame('Qassim', $po->region);
    }

    public function test_a_po_cannot_end_before_it_starts(): void
    {
        $this->assertRefused('ends_on', fn () => $this->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-04-30']));

        $po = $this->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-05-01']);
        $this->assertRefused('ends_on', fn () => app(UpdatePurchaseOrderAction::class)->execute($po, ['starts_on' => '2026-05-02'], null, $this->admin));
    }

    public function test_the_lines_need_one_service_at_least_each_once_and_active(): void
    {
        $this->assertRefused('services', fn () => $this->create(['services' => []]));

        $line = $this->payload()['services'][0];
        $this->assertRefused('services.1.main_service_id', fn () => $this->create(['services' => [$line, $line]]));

        $inactive = MainService::factory()->deactivated()->create();
        $this->assertRefused('services.0.main_service_id', fn () => $this->create([], ['main_service_id' => $inactive->id]));

        $this->assertRefused('services.0.quota_method', fn () => $this->create([], ['quota_method' => 'per_ton']));

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_a_service_deactivated_after_it_was_added_stays_on_the_po(): void
    {
        $po = $this->create();
        $this->service->forceFill(['operational_status' => 'deactivated'])->save();
        $line = $po->services->first();

        $po = app(UpdatePurchaseOrderAction::class)->execute($po, ['services' => [[
            'id' => $line->id, 'main_service_id' => $this->service->id, 'quota_method' => 'trips_only', 'max_trips' => 60, 'trip_cost' => 100, 'alert_percent' => 20,
        ]]], null, $this->admin);

        $this->assertSame(60, $po->services->first()->max_trips);
    }

    public function test_replacing_the_attachment_deletes_the_old_file(): void
    {
        Storage::fake('local');
        $po = $this->create([], [], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $old = $po->attachment_path;

        $po = app(UpdatePurchaseOrderAction::class)->execute($po, [], UploadedFile::fake()->create('b.png', 10, 'image/png'), $this->admin);

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($po->attachment_path);
    }

    // ------------------------------------------------------------ ج5: a service in a PO is in use

    public function test_a_main_service_in_an_active_po_is_in_use_even_once_it_ran_out_or_ended(): void
    {
        $this->assertFalse($this->service->hasPurchaseOrders());

        $po = $this->create();
        $this->assertTrue($this->service->hasPurchaseOrders());
        $this->assertTrue($this->service->isInUse());

        $this->charge($po->services->first(), 90, 100);
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->assertTrue($this->service->hasPurchaseOrders(), 'An exhausted, ended PO still counts while it is active.');

        app(UpdatePurchaseOrderStatusAction::class)->execute($po, 'deactivated', $this->admin);
        $this->assertFalse($this->service->hasPurchaseOrders(), 'A deactivated PO lifts the lock.');
    }

    public function test_the_sub_services_of_a_main_service_in_a_po_are_locked(): void
    {
        $sub = SubService::factory()->forService($this->service)->create();
        $this->assertFalse($sub->isLocked());

        $this->create();

        $this->assertTrue($sub->fresh()->isLocked());
    }

    // ------------------------------------------------------------ the input shape (for 6.2's requests)

    public function test_the_store_rules_accept_a_valid_input(): void
    {
        $validator = Validator::make([...$this->payload(), 'attachment' => UploadedFile::fake()->create('po.pdf', 5120, 'application/pdf')], PurchaseOrderRules::store());

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInputs(): array
    {
        return [
            'letters in the number' => [['paper_number' => '12a'], 'paper_number'],
            'eleven digits' => [['paper_number' => '12345678901'], 'paper_number'],
            'no company' => [['company_id' => null], 'company_id'],
            'a date in another format' => [['starts_on' => '01/01/2026'], 'starts_on'],
            'end before start' => [['ends_on' => '2025-12-31'], 'ends_on'],
            'no line' => [['services' => []], 'services'],
            'zero trips' => [['services.0.max_trips' => 0], 'services.0.max_trips'],
            'free trips' => [['services.0.trip_cost' => 0], 'services.0.trip_cost'],
            'three decimals' => [['services.0.trip_cost' => 10.555], 'services.0.trip_cost'],
            'alert of 100%' => [['services.0.alert_percent' => 100], 'services.0.alert_percent'],
            'unknown method' => [['services.0.quota_method' => 'per_ton'], 'services.0.quota_method'],
            'ancillary method without its cost' => [['services.0.quota_method' => 'trips_and_ancillary'], 'services.0.ancillary_cost_per_trip'],
            'a line id on create' => [['services.0.id' => 5], 'services.0.id'],
        ];
    }

    /** @dataProvider invalidInputs */
    public function test_the_store_rules_refuse(array $overrides, string $key): void
    {
        $input = $this->payload();
        foreach ($overrides as $path => $value) {
            data_set($input, $path, $value);
        }

        $this->assertArrayHasKey($key, Validator::make($input, PurchaseOrderRules::store())->errors()->toArray());
    }

    public function test_the_store_rules_refuse_an_attachment_over_5_mb_or_of_another_type(): void
    {
        foreach ([UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf'), UploadedFile::fake()->create('page.html', 10, 'text/html')] as $file) {
            $errors = Validator::make([...$this->payload(), 'attachment' => $file], PurchaseOrderRules::store())->errors()->toArray();
            $this->assertArrayHasKey('attachment', $errors);
        }
    }

    public function test_the_update_rules_let_every_field_be_left_out(): void
    {
        $this->assertFalse(Validator::make([], PurchaseOrderRules::update())->fails());
        $this->assertFalse(Validator::make(['ends_on' => '2026-09-01'], PurchaseOrderRules::update())->fails());
    }
}
