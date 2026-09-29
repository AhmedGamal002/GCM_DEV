<?php

namespace Tests\Feature\Facilities;

use App\Domain\Facilities\Actions\UpdateFacilityAction;
use App\Domain\Facilities\Exceptions\FacilityInUseException;
use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FRD V01.14 §1.8. Every test acts as ONE user — switching actingAs() users
 * inside a single test method leaks the first user (see CLAUDE.md).
 */
class FacilityManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Al Ain Landfill',
            'prefix' => 'ALF',
            'environmental_service' => 'disposal',
            'operational_status' => 'active',
        ], $overrides);
    }

    // -------------------------------------------------------------- create

    public function test_it_creates_a_facility_for_each_environmental_service(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.environmental_service', 'disposal')
            ->assertJsonPath('data.recycling_efficiency', null);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['name' => 'Sewage', 'prefix' => 'SEW', 'environmental_service' => 'sewage_treatment']))
            ->assertCreated();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload([
                'name' => 'Recycler', 'prefix' => 'REC', 'environmental_service' => 'recycle', 'recycling_efficiency' => 72.5,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.recycling_efficiency', 72.5);

        $this->assertSame(3, IntermediateFacility::count());
        $this->assertSame($this->admin->id, IntermediateFacility::where('prefix', 'ALF')->value('updated_by'));
    }

    public function test_recycling_efficiency_is_required_for_a_recycling_facility_only(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['environmental_service' => 'recycle']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recycling_efficiency']);

        // ...and is refused for the other services.
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['recycling_efficiency' => 50]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recycling_efficiency']);
    }

    public function test_recycling_efficiency_must_be_a_percentage(): void
    {
        foreach ([-1, 100.5, 'abc'] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/facilities', $this->payload(['environmental_service' => 'recycle', 'recycling_efficiency' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['recycling_efficiency']);
        }

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['environmental_service' => 'recycle', 'recycling_efficiency' => 100]))
            ->assertCreated();
    }

    public function test_the_prefix_is_three_letters_and_stored_upper_case(): void
    {
        foreach (['AB', 'ABCD', 'A1C', 'ÄBC', ''] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/facilities', $this->payload(['prefix' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['prefix']);
        }

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['prefix' => 'abc']))
            ->assertCreated()
            ->assertJsonPath('data.prefix', 'ABC');
    }

    public function test_the_prefix_is_unique_within_a_tenant_but_not_across_tenants(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/facilities', $this->payload())->assertCreated();

        // Same prefix, different case — still a duplicate.
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['name' => 'Other', 'prefix' => 'alf']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix']);

        // Another tenant may reuse it.
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        IntermediateFacility::factory()->create(['prefix' => 'ALF']);

        $this->assertSame(2, IntermediateFacility::withoutGlobalScopes()->where('prefix', 'ALF')->count());
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'prefix', 'environmental_service', 'operational_status']);
    }

    public function test_contract_dates_and_location_are_validated(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload([
                'contract_start' => '2026-06-01', 'contract_end' => '2026-01-01', 'location_url' => 'not a url',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_end', 'location_url']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload([
                'contract_number' => '00123', 'contract_start' => '2026-01-01', 'contract_end' => '2026-12-31',
                'location_url' => 'https://maps.google.com/?q=1,2', 'address' => 'Somewhere',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.contract_number', '00123');
    }

    public function test_an_untouched_rich_text_editor_is_saved_as_null(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/facilities', $this->payload(['additional_data' => '<p><br></p>']))
            ->assertCreated()
            ->assertJsonPath('data.additional_data', null);
    }

    public function test_the_logo_is_public_and_the_contract_attachment_is_private(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // A real multipart post with string values, like the browser form.
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/facilities', $this->payload([
                'logo' => UploadedFile::fake()->image('logo.png'),
                'contract_attachment' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.has_contract_attachment', true);

        $facility = IntermediateFacility::firstOrFail();
        Storage::disk('public')->assertExists($facility->logo);
        Storage::disk('local')->assertExists($facility->contract_attachment_path);
        Storage::disk('public')->assertMissing($facility->contract_attachment_path);
    }

    public function test_oversized_or_wrong_type_files_are_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/facilities', $this->payload([
                'logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png'),
                'contract_attachment' => UploadedFile::fake()->create('contract.exe', 10),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['logo', 'contract_attachment']);
    }

    public function test_the_contract_attachment_downloads_through_the_gate(): void
    {
        Storage::fake('local');
        $facility = IntermediateFacility::factory()->create(['contract_attachment_path' => 'facility-contracts/x.pdf']);
        Storage::disk('local')->put('facility-contracts/x.pdf', 'pdf-bytes');

        // Downloaded under a readable name, not the random stored one.
        $this->actingAs($this->admin, 'web')
            ->get("/api/v1/facilities/{$facility->id}/contract")
            ->assertOk()
            ->assertDownload($facility->prefix.'-contract.pdf');

        $without = IntermediateFacility::factory()->create();
        $this->actingAs($this->admin, 'web')
            ->get("/api/v1/facilities/{$without->id}/contract")
            ->assertNotFound();
    }

    // -------------------------------------------------------------- update

    public function test_update_changes_the_name_and_the_optional_data(): void
    {
        $facility = IntermediateFacility::factory()->create(['name' => 'Old', 'prefix' => 'OLD']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", [
                'name' => 'New name', 'address' => 'New address', 'contract_number' => '77',
                'additional_data' => '<p>hello</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.address', 'New address');

        $facility->refresh();
        $this->assertSame('OLD', $facility->prefix);
        $this->assertSame($this->admin->id, $facility->updated_by);
    }

    public function test_the_prefix_can_never_be_changed(): void
    {
        $facility = IntermediateFacility::factory()->create(['prefix' => 'OLD']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", ['name' => 'X', 'prefix' => 'NEW'])
            ->assertOk()
            ->assertJsonPath('data.prefix', 'OLD');
    }

    public function test_the_service_and_efficiency_can_change_while_the_facility_is_unused(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", [
                'name' => $facility->name, 'environmental_service' => 'recycle', 'recycling_efficiency' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.environmental_service', 'recycle')
            ->assertJsonPath('data.recycling_efficiency', 40);

        // Switching away from recycling clears the efficiency.
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", ['name' => $facility->name, 'environmental_service' => 'disposal'])
            ->assertOk()
            ->assertJsonPath('data.recycling_efficiency', null);
    }

    public function test_switching_to_recycle_still_needs_an_efficiency(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", ['name' => $facility->name, 'environmental_service' => 'recycle'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recycling_efficiency']);
    }

    public function test_a_locked_form_that_omits_the_service_keeps_the_stored_values(): void
    {
        $facility = IntermediateFacility::factory()->recycling(60)->create();

        // Disabled inputs aren't submitted — only the name comes through.
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.environmental_service', 'recycle')
            ->assertJsonPath('data.recycling_efficiency', 60);
    }

    public function test_the_service_and_efficiency_are_locked_once_the_facility_is_in_use(): void
    {
        $facility = new class extends IntermediateFacility
        {
            protected $table = 'intermediate_facilities';

            public function isInUse(): bool
            {
                return true;
            }
        };
        $facility->forceFill([
            'name' => 'Used', 'prefix' => 'USD', 'environmental_service' => 'recycle', 'recycling_efficiency' => 50,
        ])->save();

        $action = app(UpdateFacilityAction::class);

        // The name is still editable...
        $action->execute($facility, ['name' => 'Used renamed'], $this->admin);
        $this->assertSame('Used renamed', $facility->fresh()->name);

        // ...the service and the efficiency are not.
        $this->expectException(FacilityInUseException::class);
        $action->execute($facility, ['name' => 'Used renamed', 'environmental_service' => 'disposal'], $this->admin);
    }

    public function test_the_in_use_flag_is_exposed_for_the_edit_form(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/facilities/{$facility->id}")
            ->assertOk()
            ->assertJsonPath('data.in_use', false)
            ->assertJsonPath('data.supported_sub_services', []);
    }

    // -------------------------------------------------------------- status

    public function test_a_facility_can_be_deactivated_and_reactivated(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->assertSame('deactivated', $facility->fresh()->operational_status);
    }

    public function test_reactivating_works_too(): void
    {
        $facility = IntermediateFacility::factory()->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    public function test_only_active_and_deactivated_are_valid_statuses(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}/status", ['status' => 'on_maintenance'])
            ->assertStatus(422);
    }

    public function test_status_cannot_be_set_through_update_or_create_mass_assignment(): void
    {
        $facility = IntermediateFacility::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/facilities/{$facility->id}", ['name' => 'X', 'operational_status' => 'deactivated'])
            ->assertOk();

        $this->assertSame('active', $facility->fresh()->operational_status);
    }

    // --------------------------------------------------------------- stats

    public function test_stats_count_facilities_per_environmental_service(): void
    {
        IntermediateFacility::factory()->count(2)->disposal()->create();
        IntermediateFacility::factory()->disposal()->deactivated()->create();
        IntermediateFacility::factory()->recycling()->create();

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/facilities/stats')
            ->assertOk()
            ->assertJsonPath('data.disposal.total', 3)
            ->assertJsonPath('data.disposal.active', 2)
            ->assertJsonPath('data.recycle.total', 1)
            ->assertJsonPath('data.sewage_treatment.total', 0);
    }
}
