<?php

namespace Tests\Feature\Contractors;

use App\Models\Company;
use App\Models\Contractor;
use App\Models\MainService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FRD V01.14 §1.10 — contractors: create / view / edit / deactivate.
 */
class ContractorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create(['email' => 'de@gcm.test']);
        $this->dataEntry->assignRole('data_entry');

        $this->auditor = User::factory()->create(['email' => 'aud@gcm.test']);
        $this->auditor->assignRole('auditor');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Desert Haulage',
            'prefix' => 'DSH',
            'operational_status' => 'active',
        ], $overrides);
    }

    private function service(string $name, string $type = 'non_hazardous'): MainService
    {
        $service = new MainService(['name' => $name]);
        $service->service_type = $type;
        $service->operational_status = 'active';
        $service->save();

        return $service;
    }

    public function test_the_prefix_check_says_whether_the_short_name_is_taken(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/contractors', $this->payload())->assertCreated();

        // Taken — however it is typed.
        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/contractors/prefix-available?prefix=dsh')
            ->assertOk()
            ->assertExactJson(['available' => false]);

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/contractors/prefix-available?prefix=ZZZ')
            ->assertOk()
            ->assertExactJson(['available' => true]);
    }

    public function test_the_prefix_check_ignores_other_tenants(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        \App\Models\Contractor::factory()->create(['prefix' => 'DSH']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/contractors/prefix-available?prefix=DSH')
            ->assertOk()
            ->assertExactJson(['available' => true]);
    }

    public function test_the_prefix_check_is_closed_to_those_who_cannot_create(): void
    {
        $this->actingAs($this->auditor, 'web')
            ->getJson('/api/v1/contractors/prefix-available?prefix=ZZZ')
            ->assertForbidden();
    }

    public function test_system_admin_creates_a_contractor_with_only_the_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Desert Haulage')
            ->assertJsonPath('data.prefix', 'DSH')
            ->assertJsonPath('data.operational_status', 'active')
            ->assertJsonPath('data.supports_all_services', true);

        $contractor = Contractor::firstOrFail();
        $this->assertSame($this->admin->id, $contractor->updated_by);
    }

    public function test_the_contractor_id_is_the_prefix_a_c_and_a_running_number_per_tenant(): void
    {
        foreach (['ALN', 'GPC', 'RRE'] as $prefix) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/contractors', $this->payload(['name' => "Co {$prefix}", 'prefix' => $prefix]))
                ->assertCreated();
        }

        $this->assertSame(['ALN-C0001', 'GPC-C0002', 'RRE-C0003'], Contractor::orderBy('sequence')->pluck('code')->all());

        // another operating company counts from 1 again
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $first = Contractor::factory()->create(['prefix' => 'ALN']);
        $this->assertSame('ALN-C0001', $first->code);
    }

    public function test_a_contractor_and_a_client_company_can_share_the_same_letters(): void
    {
        $company = Company::factory()->create(['prefix' => 'ALN']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['prefix' => 'ALN']))
            ->assertCreated();

        // The "C" keeps the two IDs apart.
        $this->assertSame('ALN-0001', $company->code);
        $this->assertSame('ALN-C0001', Contractor::firstOrFail()->code);
    }

    public function test_data_entry_can_create_a_contractor(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/contractors', $this->payload())
            ->assertCreated();
    }

    public function test_data_entry_can_create_a_deactivated_contractor(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['operational_status' => 'deactivated']))
            ->assertCreated();

        $this->assertSame('deactivated', Contractor::firstOrFail()->operational_status);
    }

    public function test_name_prefix_and_status_are_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'prefix', 'operational_status']);
    }

    public function test_the_prefix_is_normalised_to_three_upper_case_letters(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['prefix' => ' dsh ']))
            ->assertCreated()
            ->assertJsonPath('data.prefix', 'DSH');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badPrefixes')]
    public function test_a_prefix_that_is_not_three_english_letters_is_rejected(string $prefix): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['prefix' => $prefix]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix']);
    }

    public static function badPrefixes(): array
    {
        return [['AB'], ['ABCD'], ['A1B'], ['ابج'], ['A B']];
    }

    public function test_the_prefix_must_be_unique_among_the_tenants_contractors(): void
    {
        Contractor::factory()->create(['prefix' => 'DSH']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['prefix' => 'dsh']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix']);
    }

    public function test_the_same_prefix_is_allowed_in_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        Contractor::factory()->create(['prefix' => 'DSH']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['prefix' => 'DSH']))
            ->assertCreated();
    }

    public function test_the_contract_end_date_cannot_be_before_the_start_date(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload([
                'contract_start_date' => '2026-05-01',
                'contract_end_date' => '2026-04-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_end_date']);
    }

    public function test_the_registration_numbers_must_be_digits_only(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload([
                'contract_number' => 'C-1',
                'cr_number' => 'abc',
                'tax_number' => '12 34',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_number', 'cr_number', 'tax_number']);
    }

    public function test_the_location_link_must_be_http_or_https(): void
    {
        // It is rendered as a clickable link, so script/data schemes must never be stored.
        foreach (['javascript:alert(1)', 'data:text/html,x', 'ftp://example.com/x', 'not a url'] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/contractors', $this->payload(['location_url' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['location_url']);
        }

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['location_url' => 'https://maps.google.com/?q=1']))
            ->assertCreated();
    }

    public function test_an_empty_rich_text_editor_is_stored_as_null(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['additional_data' => '<p><br></p>']))
            ->assertCreated();

        $this->assertNull(Contractor::firstOrFail()->additional_data);
    }

    public function test_the_trading_name_is_stored(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['trading_name' => 'Desert Trucks']))
            ->assertCreated()
            ->assertJsonPath('data.trading_name', 'Desert Trucks');
    }

    public function test_files_are_stored_and_the_attachments_stay_private(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->actingAs($this->admin, 'web')->post('/api/v1/contractors', $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png'),
            'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            'cr_attachment' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
            'tax_attachment' => UploadedFile::fake()->create('tax.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json']);

        $response->assertCreated();

        $contractor = Contractor::firstOrFail();
        Storage::disk('public')->assertExists($contractor->logo);
        Storage::disk('local')->assertExists($contractor->contract_attachment_path);
        Storage::disk('local')->assertExists($contractor->cr_attachment_path);
        Storage::disk('local')->assertExists($contractor->tax_attachment_path);

        // The private files are exposed only as authorised download routes.
        $this->assertNotNull($response->json('data.logo_url'));
        $this->assertStringContainsString("/contractors/{$contractor->id}/documents/contract", $response->json('data.contract.download_url'));
        $this->assertStringNotContainsString($contractor->contract_attachment_path, json_encode($response->json()));
    }

    public function test_an_oversized_or_wrong_type_attachment_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')->post('/api/v1/contractors', $this->payload([
            'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 5000, 'application/pdf'),
            'cr_attachment' => UploadedFile::fake()->create('cr.exe', 10, 'application/octet-stream'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_attachment', 'cr_attachment']);
    }

    public function test_the_documents_download_only_for_users_who_may_view_contractors(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')->post('/api/v1/contractors', $this->payload([
            'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $contractor = Contractor::firstOrFail();

        $this->actingAs($this->auditor, 'web')
            ->get("/api/v1/contractors/{$contractor->id}/documents/contract")
            ->assertOk();

        // nothing uploaded for the tax registration → 404; an unknown type → 404
        $this->actingAs($this->admin, 'web')->get("/api/v1/contractors/{$contractor->id}/documents/tax")->assertNotFound();
        $this->actingAs($this->admin, 'web')->get("/api/v1/contractors/{$contractor->id}/documents/passport")->assertNotFound();
    }

    public function test_a_driver_cannot_download_a_contractor_document(): void
    {
        $contractor = Contractor::factory()->create();
        $contractor->forceFill(['contract_attachment_path' => 'contractor-documents/1/contract.pdf'])->save();

        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $this->actingAs($driver, 'web')
            ->get("/api/v1/contractors/{$contractor->id}/documents/contract")
            ->assertForbidden();
    }

    // ------------------------------------------------------------ certified services

    public function test_certified_services_are_saved_and_none_means_all(): void
    {
        $a = $this->service('Skip hire');
        $b = $this->service('Liquid waste');

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['main_service_ids' => [$a->id, $b->id]]))
            ->assertCreated()
            ->assertJsonPath('data.supports_all_services', false)
            ->assertJsonCount(2, 'data.certified_services');

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['name' => 'Second', 'prefix' => 'SEC']))
            ->assertCreated()
            ->assertJsonPath('data.supports_all_services', true)
            ->assertJsonCount(0, 'data.certified_services');
    }

    public function test_certified_services_are_replaced_on_edit_and_can_be_cleared(): void
    {
        $a = $this->service('Skip hire');
        $b = $this->service('Liquid waste');
        $contractor = Contractor::factory()->create();
        $contractor->certifiedServices()->sync([$a->id]);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload(['main_service_ids' => [$b->id]]))
            ->assertOk()
            ->assertJsonPath('data.certified_services.0.name', 'Liquid waste')
            ->assertJsonCount(1, 'data.certified_services');

        // an empty value = "supports everything" (what the form sends with nothing ticked)
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload(['main_service_ids' => null]))
            ->assertOk()
            ->assertJsonPath('data.supports_all_services', true);

        $this->assertSame(0, $contractor->certifiedServices()->count());
    }

    public function test_a_service_of_another_tenant_cannot_be_certified(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = $this->service('Foreign service');
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', $this->payload(['main_service_ids' => [$foreign->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['main_service_ids.0']);
    }

    // ------------------------------------------------------------ show / edit / status

    public function test_show_returns_the_contractor_with_its_resource_counts(): void
    {
        $contractor = Contractor::factory()->create(['name' => 'Gulf Haul']);

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/contractors/{$contractor->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Gulf Haul')
            ->assertJsonPath('data.drivers_count', 0)
            ->assertJsonPath('data.vehicles_count', 0)
            ->assertJsonPath('data.containers_count', 0)
            ->assertJsonPath('data.tanks_count', 0);
    }

    public function test_every_field_except_the_prefix_is_editable(): void
    {
        $contractor = Contractor::factory()->create(['name' => 'Old name', 'prefix' => 'OLD']);
        $code = $contractor->code;

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload([
                'name' => 'New name',
                'prefix' => 'NEW',
                'trading_name' => 'Brand',
                'phone' => '+966500000000',
                'contract_number' => '12345',
                'contract_start_date' => '2026-01-01',
                'contract_end_date' => '2026-12-31',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'New name');

        $fresh = $contractor->fresh();
        // the short name (and so the contractor ID built from it) is locked
        $this->assertSame('OLD', $fresh->prefix);
        $this->assertSame($code, $fresh->code);
        $this->assertSame('Brand', $fresh->trading_name);
        $this->assertSame('2026-12-31', $fresh->contract_end_date->toDateString());
        $this->assertSame($this->dataEntry->id, $fresh->updated_by);
    }

    public function test_the_prefix_is_locked_after_creation(): void
    {
        Contractor::factory()->create(['prefix' => 'AAA']);
        $contractor = Contractor::factory()->create(['prefix' => 'BBB']);
        $code = $contractor->code;

        // any prefix sent on edit — even one another contractor already uses — is ignored, not applied
        foreach (['AAA', 'ZZZ'] as $attempt) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload(['prefix' => $attempt]))
                ->assertOk()
                ->assertJsonPath('data.prefix', 'BBB')
                ->assertJsonPath('data.code', $code);
        }

        $this->assertSame('BBB', $contractor->fresh()->prefix);
    }

    public function test_the_model_refuses_to_change_a_saved_prefix_from_any_code_path(): void
    {
        $contractor = Contractor::factory()->create(['prefix' => 'BBB']);

        $contractor->prefix = 'CCC';
        $contractor->save();

        $this->assertSame('BBB', $contractor->fresh()->prefix);
    }

    public function test_editing_cannot_change_the_status_or_the_code(): void
    {
        $contractor = Contractor::factory()->create();
        $code = $contractor->code;

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload([
                'operational_status' => 'deactivated',
                'code' => 'HACK-C9999',
            ]))
            ->assertOk();

        $fresh = $contractor->fresh();
        $this->assertSame('active', $fresh->operational_status);
        $this->assertSame($code, $fresh->code);
    }

    public function test_an_uploaded_replacement_removes_the_old_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')->post('/api/v1/contractors', $this->payload([
            'contract_attachment' => UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $contractor = Contractor::firstOrFail();
        $old = $contractor->contract_attachment_path;

        // editing without a file keeps it
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload())->assertOk();
        $this->assertSame($old, $contractor->fresh()->contract_attachment_path);

        $this->actingAs($this->admin, 'web')->post("/api/v1/contractors/{$contractor->id}", $this->payload([
            '_method' => 'PATCH',
            'contract_attachment' => UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $new = $contractor->fresh()->contract_attachment_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_the_status_is_changed_through_its_own_endpoint_by_admin_and_data_entry(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'on_maintenance'])
            ->assertStatus(422);
    }

    public function test_deactivating_a_contractor_does_not_touch_its_resources(): void
    {
        $contractor = Contractor::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        // FRD §1.10 is silent about a cascade — the status is only the contractor's own.
        $this->assertSame('active', $vehicle->fresh()->operational_status);
    }

    // ------------------------------------------------------------ access control

    public function test_the_auditor_can_view_and_export_but_not_write(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->auditor, 'web')->getJson('/api/v1/contractors')->assertOk();
        $this->actingAs($this->auditor, 'web')->getJson("/api/v1/contractors/{$contractor->id}")->assertOk();
        $this->actingAs($this->auditor, 'web')->getJson('/api/v1/contractors/stats')->assertOk();
        $this->actingAs($this->auditor, 'web')->get('/api/v1/contractors/export')->assertOk();

        $this->actingAs($this->auditor, 'web')->postJson('/api/v1/contractors', $this->payload())->assertForbidden();
        $this->actingAs($this->auditor, 'web')->patchJson("/api/v1/contractors/{$contractor->id}", $this->payload())->assertForbidden();
        $this->actingAs($this->auditor, 'web')->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_the_pages_hide_the_write_controls_from_the_auditor(): void
    {
        $this->withoutVite();
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->auditor, 'web')->get('/app/contractor/list')->assertOk()->assertSee('can_manage: false', false);
        $this->actingAs($this->auditor, 'web')->get("/app/contractor/view/{$contractor->id}")->assertOk()->assertSee('can_manage: false', false);
        $this->actingAs($this->auditor, 'web')->get('/app/contractor/add')->assertForbidden();
        $this->actingAs($this->auditor, 'web')->get("/app/contractor/edit/{$contractor->id}")->assertForbidden();
    }

    public function test_the_pages_render_for_a_manager(): void
    {
        $this->withoutVite();
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->dataEntry, 'web')->get('/app/contractor/list')->assertOk()->assertSee('can_manage: true', false);
        $this->actingAs($this->dataEntry, 'web')->get('/app/contractor/add')->assertOk();
        $this->actingAs($this->dataEntry, 'web')->get("/app/contractor/view/{$contractor->id}")->assertOk();
        $this->actingAs($this->dataEntry, 'web')->get("/app/contractor/edit/{$contractor->id}")->assertOk();
    }

    public function test_a_driver_has_no_access(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/contractors')->assertForbidden();
        $this->actingAs($driver, 'web')->getJson('/api/v1/contractors/stats')->assertForbidden();
        $this->actingAs($driver, 'web')->postJson('/api/v1/contractors', $this->payload())->assertForbidden();
    }

    public function test_a_contractor_of_another_tenant_is_invisible(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = Contractor::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/contractors/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/contractors/{$foreign->id}", $this->payload())->assertNotFound();
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors')->assertJsonPath('meta.total', 0);
    }

    // ------------------------------------------------------------ list / stats

    public function test_the_list_is_paginated_searchable_filterable_and_sortable(): void
    {
        Contractor::factory()->create(['name' => 'Zeta Haul', 'prefix' => 'ZET', 'trading_name' => 'Zeta Brand']);
        Contractor::factory()->create(['name' => 'Alpha Haul', 'prefix' => 'ALP']);
        Contractor::factory()->deactivated()->create(['name' => 'Gamma Haul', 'prefix' => 'GAM']);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'Alpha Haul');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors?sort_by=name&sort_dir=desc')
            ->assertJsonPath('data.0.name', 'Zeta Haul');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors?operational_status=deactivated')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Gamma Haul');

        // name, trading name, short name and ID are all searchable — they are what the table shows
        foreach (['Zeta Haul', 'Zeta Brand', 'ZET', 'ZET-C0001'] as $term) {
            $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors?search='.urlencode($term))
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.name', 'Zeta Haul');
        }
    }

    public function test_the_list_shows_how_many_drivers_vehicles_containers_and_tanks_each_contractor_supplies(): void
    {
        $contractor = Contractor::factory()->create();
        Contractor::factory()->create();

        \App\Models\Vehicle::factory()->count(2)->create(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        \App\Models\Asset::factory()->create(['asset_type' => 'container', 'affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        \App\Models\Asset::factory()->count(3)->create(['asset_type' => 'tank', 'affiliation' => 'contractor', 'contractor_id' => $contractor->id]);

        $row = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors')->json('data'))
            ->firstWhere('id', $contractor->id);

        $this->assertSame(2, $row['vehicles_count']);
        $this->assertSame(1, $row['containers_count']);
        $this->assertSame(3, $row['tanks_count']);
        $this->assertSame(0, $row['drivers_count']);
    }

    public function test_the_stats_count_contractors_and_the_resources_they_supply(): void
    {
        $contractor = Contractor::factory()->create();
        Contractor::factory()->deactivated()->create();

        // GCM's own resources are not contractor resources
        \App\Models\Vehicle::factory()->create();
        \App\Models\Vehicle::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        \App\Models\Asset::factory()->create(['asset_type' => 'container', 'affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        \App\Models\Asset::factory()->create(['asset_type' => 'tank']);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/contractors/stats')
            ->assertOk()
            ->assertJsonPath('data.contractors.total', 2)
            ->assertJsonPath('data.contractors.active', 1)
            ->assertJsonPath('data.contractors.deactivated', 1)
            ->assertJsonPath('data.vehicles', 1)
            ->assertJsonPath('data.assets.container', 1)
            ->assertJsonPath('data.assets.tank', 0)
            ->assertJsonPath('data.drivers', 0);
    }

    public function test_the_foreign_key_stops_a_contractor_with_resources_from_being_deleted(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('SQLite cannot add the foreign key in an ALTER — MySQL enforces it.');
        }

        $contractor = Contractor::factory()->create();
        \App\Models\Vehicle::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $contractor->delete();
    }
}
