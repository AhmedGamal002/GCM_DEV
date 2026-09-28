<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FRD V01.14 §1.11 — client companies: create / view / edit / deactivate.
 */
class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

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
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Al Noor Construction',
            'prefix' => 'ALN',
            'operational_status' => 'active',
        ], $overrides);
    }

    public function test_system_admin_creates_a_company_with_only_the_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Al Noor Construction')
            ->assertJsonPath('data.prefix', 'ALN')
            ->assertJsonPath('data.operational_status', 'active');

        $company = Company::firstOrFail();
        $this->assertSame($this->admin->id, $company->updated_by);
        // FRD: the short name is mixed with a number — the company ID is prefix + number.
        $this->assertSame('ALN-0001', $company->code);
        $this->assertSame('ALN-0001', $response->json('data.code'));
    }

    public function test_the_company_id_is_the_prefix_plus_a_running_number_per_tenant(): void
    {
        foreach (['ALN', 'GPC', 'RRE'] as $prefix) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/companies', $this->payload(['name' => "Co {$prefix}", 'prefix' => $prefix]))
                ->assertCreated();
        }

        $this->assertSame(['ALN-0001', 'GPC-0002', 'RRE-0003'], Company::orderBy('sequence')->pluck('code')->all());

        // another operating company counts from 1 again
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $first = Company::factory()->create(['prefix' => 'ALN']);
        $this->assertSame('ALN-0001', $first->code);
    }

    public function test_data_entry_can_create_a_company(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/companies', $this->payload())
            ->assertCreated();
    }

    public function test_data_entry_can_create_a_deactivated_company(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/companies', $this->payload(['operational_status' => 'deactivated']))
            ->assertCreated();

        $this->assertSame('deactivated', Company::firstOrFail()->operational_status);
    }

    public function test_name_prefix_and_status_are_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'prefix', 'operational_status']);
    }

    public function test_the_prefix_is_normalised_to_three_upper_case_letters(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['prefix' => ' aln ']))
            ->assertCreated()
            ->assertJsonPath('data.prefix', 'ALN');
    }

    /** @dataProvider badPrefixes */
    public function test_a_prefix_that_is_not_three_english_letters_is_rejected(string $prefix): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['prefix' => $prefix]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix']);
    }

    public static function badPrefixes(): array
    {
        return [['AB'], ['ABCD'], ['A1B'], ['ابج'], ['A B']];
    }

    public function test_the_prefix_must_be_unique_within_the_tenant(): void
    {
        Company::factory()->create(['prefix' => 'ALN']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['prefix' => 'aln']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix']);
    }

    public function test_the_same_prefix_is_allowed_in_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        Company::factory()->create(['prefix' => 'ALN']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['prefix' => 'ALN']))
            ->assertCreated();
    }

    public function test_the_contract_end_date_cannot_be_before_the_start_date(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload([
                'contract_start_date' => '2026-05-01',
                'contract_end_date' => '2026-04-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_end_date']);
    }

    public function test_the_registration_numbers_must_be_digits_only(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload([
                'contract_number' => 'C-1',
                'cr_number' => 'abc',
                'tax_number' => '12 34',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_number', 'cr_number', 'tax_number']);
    }

    public function test_email_and_location_link_are_validated(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['email' => 'nope', 'location_url' => 'not a url']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'location_url']);
    }

    public function test_the_location_link_must_be_http_or_https(): void
    {
        // It is rendered as a clickable link, so script/data schemes must never be stored.
        foreach (['javascript:alert(1)', 'data:text/html,x', 'ftp://example.com/x'] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/companies', $this->payload(['location_url' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['location_url']);
        }

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['location_url' => 'https://maps.google.com/?q=1']))
            ->assertCreated();
    }

    public function test_an_empty_rich_text_editor_is_stored_as_null(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', $this->payload(['additional_data' => '<p><br></p>']))
            ->assertCreated();

        $this->assertNull(Company::firstOrFail()->additional_data);
    }

    public function test_files_are_stored_and_the_attachments_stay_private(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->actingAs($this->admin, 'web')->post('/api/v1/companies', $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png'),
            'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            'cr_attachment' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
            'tax_attachment' => UploadedFile::fake()->create('tax.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json']);

        $response->assertCreated();

        $company = Company::firstOrFail();
        Storage::disk('public')->assertExists($company->logo);
        Storage::disk('local')->assertExists($company->contract_attachment_path);
        Storage::disk('local')->assertExists($company->cr_attachment_path);
        Storage::disk('local')->assertExists($company->tax_attachment_path);

        // The private files are exposed only as authorised download routes.
        $this->assertNotNull($response->json('data.logo_url'));
        $this->assertStringContainsString("/companies/{$company->id}/documents/contract", $response->json('data.contract.download_url'));
        $this->assertStringNotContainsString($company->contract_attachment_path, json_encode($response->json()));
    }

    public function test_an_oversized_or_wrong_type_attachment_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')->post('/api/v1/companies', $this->payload([
            'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 5000, 'application/pdf'),
            'cr_attachment' => UploadedFile::fake()->create('cr.exe', 10, 'application/octet-stream'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_attachment', 'cr_attachment']);
    }

    public function test_show_returns_the_company_with_placeholder_counts(): void
    {
        $company = Company::factory()->create(['name' => 'Gulf Petro']);

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Gulf Petro')
            ->assertJsonPath('data.projects_count', 0)
            ->assertJsonPath('data.users_count', 0)
            ->assertJsonPath('data.stats.trips', 0);
    }

    public function test_every_field_except_the_prefix_is_editable(): void
    {
        $company = Company::factory()->create(['name' => 'Old name', 'prefix' => 'OLD']);
        $code = $company->code;

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/companies/{$company->id}", $this->payload([
                'name' => 'New name',
                'prefix' => 'NEW',
                'business_sector' => 'Retail',
                'phone' => '+966500000000',
                'contract_number' => '12345',
                'contract_start_date' => '2026-01-01',
                'contract_end_date' => '2026-12-31',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'New name');

        $fresh = $company->fresh();
        // the short name (and so the company ID built from it) is locked
        $this->assertSame('OLD', $fresh->prefix);
        $this->assertSame($code, $fresh->code);
        $this->assertSame('Retail', $fresh->business_sector);
        $this->assertSame('2026-12-31', $fresh->contract_end_date->toDateString());
        $this->assertSame($this->dataEntry->id, $fresh->updated_by);
    }

    public function test_a_company_can_keep_its_own_prefix_when_edited(): void
    {
        $company = Company::factory()->create(['prefix' => 'ALN']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$company->id}", $this->payload(['prefix' => 'ALN']))
            ->assertOk();
    }

    public function test_the_prefix_is_locked_after_creation(): void
    {
        Company::factory()->create(['prefix' => 'AAA']);
        $company = Company::factory()->create(['prefix' => 'BBB']);
        $code = $company->code;

        // any prefix sent on edit — even one another company already uses — is ignored, not applied
        foreach (['AAA', 'ZZZ'] as $attempt) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/companies/{$company->id}", $this->payload(['prefix' => $attempt]))
                ->assertOk()
                ->assertJsonPath('data.prefix', 'BBB')
                ->assertJsonPath('data.code', $code);
        }

        $this->assertSame('BBB', $company->fresh()->prefix);
    }

    public function test_the_model_refuses_to_change_a_saved_prefix_from_any_code_path(): void
    {
        $company = Company::factory()->create(['prefix' => 'BBB']);

        $company->prefix = 'CCC';
        $company->save();

        $this->assertSame('BBB', $company->fresh()->prefix);
    }

    public function test_editing_cannot_change_the_status_or_the_code(): void
    {
        $company = Company::factory()->create();
        $code = $company->code;

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$company->id}", $this->payload([
                'prefix' => $company->prefix,
                'code' => '000000',
                'operational_status' => 'deactivated',
            ]))
            ->assertOk();

        $fresh = $company->fresh();
        $this->assertSame($code, $fresh->code);
        $this->assertSame('active', $fresh->operational_status);
    }

    public function test_re_uploading_replaces_the_old_file_and_no_file_keeps_it(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();

        $this->actingAs($this->admin, 'web')->post("/api/v1/companies/{$company->id}", $this->payload([
            '_method' => 'PATCH',
            'prefix' => $company->prefix,
            'contract_attachment' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $first = $company->fresh()->contract_attachment_path;
        Storage::disk('local')->assertExists($first);

        // Saving again without picking a file keeps the stored one.
        $this->actingAs($this->admin, 'web')->post("/api/v1/companies/{$company->id}", $this->payload([
            '_method' => 'PATCH',
            'prefix' => $company->prefix,
        ]), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame($first, $company->fresh()->contract_attachment_path);

        // A new file replaces it and the old one is deleted.
        $this->actingAs($this->admin, 'web')->post("/api/v1/companies/{$company->id}", $this->payload([
            '_method' => 'PATCH',
            'prefix' => $company->prefix,
            'contract_attachment' => UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $second = $company->fresh()->contract_attachment_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_status_can_be_deactivated_and_reactivated(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->assertSame($this->dataEntry->id, $company->fresh()->updated_by);
    }

    public function test_reactivating_works_from_the_deactivated_state(): void
    {
        $company = Company::factory()->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'on_maintenance'])
            ->assertStatus(422);
    }

    public function test_there_is_no_hard_delete_route(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->deleteJson("/api/v1/companies/{$company->id}")
            ->assertStatus(405);
    }

    public function test_the_attachment_download_streams_the_private_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')->post('/api/v1/companies', $this->payload([
            'tax_attachment' => UploadedFile::fake()->create('tax.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $company = Company::firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->get("/api/v1/companies/{$company->id}/documents/tax")
            ->assertOk();

        // Nothing was uploaded for the contract, and the type is validated.
        $this->actingAs($this->admin, 'web')->get("/api/v1/companies/{$company->id}/documents/contract")->assertNotFound();
        $this->actingAs($this->admin, 'web')->get("/api/v1/companies/{$company->id}/documents/passwd")->assertNotFound();
    }

    public function test_the_first_page_of_the_list_is_sorted_by_name(): void
    {
        Company::factory()->create(['name' => 'Zeta Co']);
        Company::factory()->create(['name' => 'Alpha Co']);

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha Co')
            ->assertJsonPath('data.1.name', 'Zeta Co');
    }

    public function test_the_list_is_paginated_searchable_filterable_and_sortable(): void
    {
        Company::factory()->count(20)->create();
        Company::factory()->create(['name' => 'Needle Corp', 'prefix' => 'NDL']);
        Company::factory()->deactivated()->create(['name' => 'Sleeping Corp', 'prefix' => 'SLP']);

        $paged = $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?per_page=10');
        $paged->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 22);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?search=Needle')
            ->assertJsonPath('meta.total', 1);
        // the short name and the ID are searchable too — both are shown in the table
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?search=NDL')
            ->assertJsonPath('meta.total', 1);
        $code = Company::where('prefix', 'NDL')->value('code');
        $this->actingAs($this->admin, 'web')->getJson("/api/v1/companies?search={$code}")
            ->assertJsonPath('data.0.name', 'Needle Corp');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?operational_status=deactivated')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Sleeping Corp');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?sort_by=name&sort_dir=desc&per_page=1')
            ->assertJsonPath('data.0.name', Company::orderByDesc('name')->value('name'));

        // sort_by is an allowlist, never the raw request value
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?sort_by=password')
            ->assertOk();
    }
}
