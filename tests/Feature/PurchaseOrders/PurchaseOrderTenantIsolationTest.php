<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Quota\DeductQuotaAction;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Domain\PurchaseOrders\Support\PurchaseOrderRules;
use App\Exceptions\TenantContextMissingException;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * POs, their lines and the ledger are tenant-scoped like every other table:
 * nothing of another tenant is read, charged, released, blocked or accepted.
 */
class PurchaseOrderTenantIsolationTest extends PurchaseOrderTestCase
{
    private Tenant $other;

    private PurchaseOrderService $foreignLine;

    private Company $foreignCompany;

    private Project $foreignProject;

    private MainService $foreignService;

    protected function setUp(): void
    {
        parent::setUp();

        // A second tenant with a company of the SAME prefix and its own PO 1200.
        $this->other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $this->other);
        $otherAdmin = User::factory()->create();
        $this->foreignCompany = Company::factory()->create(['prefix' => 'ALN']);
        $this->foreignProject = Project::factory()->for($this->foreignCompany)->create();
        $this->foreignService = MainService::factory()->create();
        $po = app(CreatePurchaseOrderAction::class)->execute([
            ...$this->payload(),
            'company_id' => $this->foreignCompany->id,
            'project_id' => $this->foreignProject->id,
            'services' => [[...$this->payload()['services'][0], 'main_service_id' => $this->foreignService->id]],
        ], null, $otherAdmin);
        $this->foreignLine = $po->services->first();
        app(DeductQuotaAction::class)->execute($this->foreignLine, 77, 100, 0, $otherAdmin);

        app()->instance('tenant', $this->tenant);
    }

    public function test_the_same_number_exists_in_two_tenants(): void
    {
        $po = app(CreatePurchaseOrderAction::class)->execute($this->payload(), null, $this->admin);

        $this->assertSame('ALN-1200', $po->number);
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_pos_lines_and_ledger_rows_of_another_tenant_are_invisible(): void
    {
        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, PurchaseOrderService::count());
        $this->assertSame(0, QuotaLedgerEntry::count());
        $this->assertNull(PurchaseOrderService::find($this->foreignLine->id));
    }

    public function test_another_tenants_line_cannot_be_charged(): void
    {
        $this->expectException(ModelNotFoundException::class);
        app(DeductQuotaAction::class)->execute($this->foreignLine, 1, 50, 0, $this->admin);
    }

    public function test_releasing_a_trip_id_never_touches_another_tenants_ledger(): void
    {
        $this->assertSame(0, app(ReleaseQuotaAction::class)->execute(77, $this->admin));

        app()->instance('tenant', $this->other);
        $this->assertSame(1, QuotaBalances::for(PurchaseOrderService::find($this->foreignLine->id))->tripsUsed);
    }

    public function test_another_tenants_company_project_or_service_cannot_be_used(): void
    {
        foreach ([
            'company_id' => ['company_id' => $this->foreignCompany->id],
            'project_id' => ['project_id' => $this->foreignProject->id],
        ] as $key => $overrides) {
            try {
                app(CreatePurchaseOrderAction::class)->execute($this->payload($overrides), null, $this->admin);
                $this->fail("A foreign {$key} was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($key, $e->errors());
            }
        }

        try {
            app(CreatePurchaseOrderAction::class)->execute($this->payload([], ['main_service_id' => $this->foreignService->id]), null, $this->admin);
            $this->fail('A foreign main service was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('services.0.main_service_id', $e->errors());
        }

        $errors = Validator::make($this->payload([
            'company_id' => $this->foreignCompany->id,
            'project_id' => $this->foreignProject->id,
        ], ['main_service_id' => $this->foreignService->id]), PurchaseOrderRules::store())->errors()->toArray();

        $this->assertArrayHasKey('company_id', $errors);
        $this->assertArrayHasKey('project_id', $errors);
        $this->assertArrayHasKey('services.0.main_service_id', $errors);
    }

    public function test_the_ledger_fails_closed_without_a_tenant(): void
    {
        app()->forgetInstance('tenant');

        $this->expectException(TenantContextMissingException::class);
        QuotaLedgerEntry::count();
    }
}
