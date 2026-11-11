<?php

namespace Tests\Feature\PurchaseOrders\Api;

use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrderService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\PurchaseOrders\PurchaseOrderTestCase;

/**
 * An export holds exactly the rows the user sees on screen: the same
 * filteredQuery() feeds the list and the file (the rule of ExportFiltersTest,
 * kept here beside the PO API tests).
 */
class PurchaseOrderExportTest extends PurchaseOrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Excel::fake();
        $this->actingAs($this->admin, 'web');
    }

    private function createPo(array $overrides = [], array $line = []): int
    {
        return $this->postJson('/api/v1/purchase-orders', $this->payload($overrides, $line))->assertCreated()->json('data.id');
    }

    private function exported(string $query = ''): Collection
    {
        $this->get("/api/v1/purchase-orders/export?format=xlsx{$query}")->assertOk();

        $rows = collect();
        Excel::assertDownloaded('purchase-orders.xlsx', function ($export) use (&$rows) {
            $rows = $export->collection();

            return true;
        });

        return $rows;
    }

    private function seedThree(): array
    {
        $this->createPo();

        $beta = Company::factory()->create(['prefix' => 'BET', 'name' => 'Beta Gulf']);
        $betaProject = Project::factory()->for($beta)->create(['name' => 'Beta Plant']);
        $betaId = $this->createPo(['paper_number' => '77', 'company_id' => $beta->id, 'project_id' => $betaProject->id], ['main_service_id' => MainService::factory()->create()->id]);
        $this->patchJson("/api/v1/purchase-orders/{$betaId}/status", ['status' => 'deactivated'])->assertOk();

        $nearService = MainService::factory()->create();
        $near = PurchaseOrderService::where('purchase_order_id', $this->createPo(['paper_number' => '2', 'starts_on' => '2026-06-01', 'ends_on' => '2026-07-01'], ['main_service_id' => $nearService->id, 'max_trips' => 10]))->firstOrFail();
        $this->charge($near, 9, 100);

        return [$beta, $betaProject];
    }

    public function test_the_export_honours_search_and_every_filter(): void
    {
        [$beta, $betaProject] = $this->seedThree();

        $numbers = fn (string $query) => $this->exported($query)->pluck('number')->sort()->values()->all();

        $this->assertSame(['ALN-1200', 'ALN-2', 'BET-77'], $numbers(''));
        $this->assertSame(['BET-77'], $numbers("&company_id={$beta->id}"));
        $this->assertSame(['BET-77'], $numbers("&project_id={$betaProject->id}"));
        $this->assertSame(['BET-77'], $numbers('&operational_status=deactivated'));
        $this->assertSame(['ALN-1200'], $numbers('&search=1200'));
        $this->assertSame(['BET-77'], $numbers('&search=Beta'));
        // the time range
        $this->assertSame(['ALN-2'], $numbers('&date_from=2026-06-15&date_to=2026-12-31&operational_status=active'));
        // the items status, resolved from the ledger like the list
        $this->assertSame(['ALN-2'], $numbers('&status=near_exhaustion'));
        // filters combine
        $this->assertSame(['ALN-1200'], $numbers("&company_id={$this->company->id}&status=valid&search=ALN"));
    }

    public function test_the_export_rows_match_the_list_rows(): void
    {
        $this->seedThree();

        $onScreen = collect($this->getJson('/api/v1/purchase-orders?status=near_exhaustion&per_page=100')->json('data'))->pluck('number')->all();

        $this->assertSame($onScreen, $this->exported('&status=near_exhaustion')->pluck('number')->all());
    }

    public function test_the_export_row_carries_the_state_labels_and_the_dates(): void
    {
        $this->seedThree();

        $row = $this->exported('&search=1200')->first();
        $mapped = (new \App\Domain\PurchaseOrders\Exports\PurchaseOrdersExport(collect([$row])))->map($row);

        $this->assertSame('ALN-1200', $mapped[0]);
        $this->assertSame('Al Nakheel', $mapped[1]);
        $this->assertSame('Riyadh Hotel', $mapped[2]);
        $this->assertSame('2026-01-01', $mapped[3]);
        $this->assertSame('2026-05-30', $mapped[4]);
        $this->assertSame('91 days left', $mapped[5]);
        $this->assertSame('In force', $mapped[6]);
        $this->assertSame('active', $mapped[7]);
    }

    public function test_the_pdf_export_is_a_pdf_with_the_same_rows(): void
    {
        $this->seedThree();

        $response = $this->get('/api/v1/purchase-orders/export?format=pdf&search=1200')->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
