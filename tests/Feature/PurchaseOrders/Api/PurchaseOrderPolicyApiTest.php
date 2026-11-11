<?php

namespace Tests\Feature\PurchaseOrders\Api;

use App\Models\Company;
use App\Models\Contractor;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\PurchaseOrders\PurchaseOrderTestCase;

/**
 * Part 6.2 — who may do what (FRD V01.14 §1.13 and the role pages):
 *  - create / edit / deactivate: the System Admin only — Data Entry has NO access to
 *    POs at all, not even to read them;
 *  - view / export: System Admin and Auditor;
 *  - a client Project Manager sees the POs of their own projects, every amount, no export;
 *  - every other role (client Project Auditor, contractor user, driver): nothing.
 * One test = one actor (the actingAs trap): each test builds its own user.
 */
class PurchaseOrderPolicyApiTest extends PurchaseOrderTestCase
{
    private PurchaseOrder $order;

    private PurchaseOrder $otherOrder;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $line = $this->line(po: ['number' => 'ALN-1200']);
        $this->order = $line->purchaseOrder;

        // A PO of ANOTHER project of the same company, to tell "my project" from "not mine".
        $this->otherProject = Project::factory()->for($this->company)->create(['name' => 'Other Tower']);
        $this->otherOrder = $this->line(project: $this->otherProject, po: ['number' => 'ALN-1300'])->purchaseOrder;

        Storage::fake('local');
        Storage::disk('local')->put('purchase-order-attachments/1/po.pdf', 'pdf');
        $this->order->forceFill(['attachment_path' => 'purchase-order-attachments/1/po.pdf'])->save();
    }

    private function as(string $role, array $state = []): static
    {
        $user = User::factory()->create($state);
        $user->assignRole($role);

        return $this->actingAs($user, 'web');
    }

    private function asClient(string $role, bool $allProjects, array $projects = []): static
    {
        $user = User::factory()->client($this->company, $role, $allProjects)->create();

        foreach ($projects as $project) {
            $user->projects()->attach($project->id);
        }

        return $this->actingAs($user, 'web');
    }

    // ── System Admin: everything ────────────────────────────────────

    public function test_the_system_admin_can_do_everything(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertOk();
        $this->getJson('/api/v1/purchase-orders/stats')->assertOk();
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertOk();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertOk();
    }

    // ── Auditor: read + export, nothing else ────────────────────────

    public function test_the_auditor_reads_and_exports_but_cannot_change(): void
    {
        Excel::fake();
        $this->as('auditor');

        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertOk();
        $this->getJson('/api/v1/purchase-orders/stats')->assertOk();
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertOk();
        $this->get('/api/v1/purchase-orders/export?format=xlsx')->assertOk();

        $this->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '5']))->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}", ['region' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    // ── Data Entry: no access at all (the one exception to "admin parity") ──

    public function test_data_entry_has_no_access_not_even_to_read(): void
    {
        $this->as('data_entry');

        $this->getJson('/api/v1/purchase-orders')->assertForbidden();
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertForbidden();
        $this->getJson('/api/v1/purchase-orders/stats')->assertForbidden();
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertForbidden();
        $this->get('/api/v1/purchase-orders/export?format=xlsx')->assertForbidden();
        $this->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '5']))->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}", ['region' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    // ── Client Project Manager: own projects, read only ─────────────

    public function test_a_manager_of_all_projects_sees_the_pos_of_the_company_with_every_amount(): void
    {
        $this->asClient('client_project_manager', true);

        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.services.0.trip_cost', 50);
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertOk()
            ->assertJsonPath('data.services.0.max_trip_cost', 5000);
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertOk();
    }

    public function test_a_manager_of_chosen_projects_sees_only_their_pos(): void
    {
        $this->asClient('client_project_manager', false, [$this->project]);

        $numbers = collect($this->getJson('/api/v1/purchase-orders')->assertOk()->json('data'))->pluck('number')->all();
        $this->assertSame(['ALN-1200'], $numbers);

        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertOk();
        // a PO of a project they are not assigned to: forbidden, in the list, the card figures and the file
        $this->getJson("/api/v1/purchase-orders/{$this->otherOrder->id}")->assertForbidden();
        $this->get("/api/v1/purchase-orders/{$this->otherOrder->id}/attachment")->assertForbidden();
    }

    public function test_the_manager_stats_cover_only_their_projects(): void
    {
        $this->asClient('client_project_manager', false, [$this->project]);

        $this->getJson('/api/v1/purchase-orders/stats')->assertOk()
            ->assertJsonPath('data.purchase_orders', 1)
            ->assertJsonPath('data.projects', 1);
    }

    public function test_a_manager_of_no_project_sees_nothing(): void
    {
        $this->asClient('client_project_manager', false, []);

        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchase-orders/stats')->assertOk()->assertJsonPath('data.purchase_orders', 0);
    }

    public function test_a_manager_of_another_company_sees_nothing_of_this_one(): void
    {
        $other = Company::factory()->create(['prefix' => 'BET']);
        $user = User::factory()->client($other, 'client_project_manager', true)->create();

        $this->actingAs($user, 'web')->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertForbidden();
    }

    public function test_a_manager_cannot_export_or_change_anything(): void
    {
        $this->asClient('client_project_manager', true);

        $this->get('/api/v1/purchase-orders/export?format=xlsx')->assertForbidden();
        $this->postJson('/api/v1/purchase-orders', $this->payload(['paper_number' => '5']))->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}", ['region' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    // ── Everyone else ───────────────────────────────────────────────

    public function test_a_client_project_auditor_has_no_access(): void
    {
        $this->asClient('client_project_auditor', true);

        $this->getJson('/api/v1/purchase-orders')->assertForbidden();
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertForbidden();
        $this->getJson('/api/v1/purchase-orders/stats')->assertForbidden();
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertForbidden();
    }

    public function test_a_contractor_user_has_no_access(): void
    {
        $user = User::factory()->contractorUser(Contractor::factory()->create())->create();

        $this->actingAs($user, 'web')->getJson('/api/v1/purchase-orders')->assertForbidden();
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertForbidden();
        $this->getJson('/api/v1/purchase-orders/stats')->assertForbidden();
    }

    public function test_a_driver_has_no_access(): void
    {
        $this->as('driver');

        $this->getJson('/api/v1/purchase-orders')->assertForbidden();
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertForbidden();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_a_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/purchase-orders')->assertUnauthorized();
        $this->postJson('/api/v1/purchase-orders', [])->assertUnauthorized();
    }

    // ── Real Bearer token (the actingAs trap: it skips the real auth pipeline) ──

    public function test_the_api_works_with_a_real_bearer_token(): void
    {
        $token = $this->admin->createToken('postman')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertOk()->assertJsonPath('data.number', 'ALN-1200');
    }

    public function test_a_data_entry_token_is_still_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('data_entry');

        $this->withToken($user->createToken('postman')->plainTextToken)
            ->getJson('/api/v1/purchase-orders')->assertForbidden();
    }

    // ── Tenant isolation ────────────────────────────────────────────

    public function test_another_tenants_admin_sees_none_of_these_pos(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $theirAdmin = User::factory()->create();
        $theirAdmin->assignRole('system_admin');

        $this->actingAs($theirAdmin, 'web')->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")->assertNotFound();
        $this->get("/api/v1/purchase-orders/{$this->order->id}/attachment")->assertNotFound();
        $this->patchJson("/api/v1/purchase-orders/{$this->order->id}/status", ['status' => 'deactivated'])->assertNotFound();
        $this->getJson('/api/v1/purchase-orders/stats')->assertOk()->assertJsonPath('data.purchase_orders', 0);
    }

    public function test_a_po_cannot_be_created_with_another_tenants_company_project_or_service(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreignCompany = Company::factory()->create(['prefix' => 'ZZZ']);
        $foreignProject = Project::factory()->for($foreignCompany)->create();
        $foreignService = MainService::factory()->create();

        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/purchase-orders', $this->payload([
            'company_id' => $foreignCompany->id,
            'project_id' => $foreignProject->id,
        ], ['main_service_id' => $foreignService->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id', 'project_id', 'services.0.main_service_id']);
    }

    public function test_the_client_cannot_use_the_staff_upload_route_even_for_their_own_po(): void
    {
        Storage::fake('local');
        $this->asClient('client_project_manager', true);

        $this->post("/api/v1/purchase-orders/{$this->order->id}", [
            '_method' => 'PATCH',
            'attachment' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

    }
}
