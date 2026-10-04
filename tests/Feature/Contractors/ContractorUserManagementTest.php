<?php

namespace Tests\Feature\Contractors;

use App\Models\Contractor;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FRD V01.14 §1.4 — contractor users ("مستخدم متعهد"): create / view / edit
 * through /api/v1/contractor-users, plus the contractor's representative
 * account (§1.10.2).
 */
class ContractorUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Contractor $desert;

    private Contractor $gulf;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->desert = Contractor::factory()->create(['name' => 'Desert Haulage', 'prefix' => 'DSH']);
        $this->gulf = Contractor::factory()->create(['name' => 'Gulf Logistics', 'prefix' => 'GLG']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Omar Hauler',
            'email' => 'omar@desert.test',
            'phone' => '+966500000301',
            'password' => 'Str0ng!Pass#1',
            'password_confirmation' => 'Str0ng!Pass#1',
            'status' => 'active',
            'contractor_id' => $this->desert->id,
        ], $overrides);
    }

    private function createContractorUser(array $overrides = []): User
    {
        $id = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', $this->payload($overrides))
            ->assertCreated()
            ->json('data.id');

        return User::findOrFail($id);
    }

    /** A contractor user made straight in the database — for tests whose actor is NOT the admin (see the actingAs caching note in CLAUDE.md). */
    private function contractorUser(array $attributes = []): User
    {
        return User::factory()->contractorUser($this->desert)->create($attributes + ['phone' => '+9665000'.random_int(10000, 99999)]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // ------------------------------------------------------------------ create

    public function test_a_contractor_user_is_created_with_the_fixed_role_and_affiliation(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', $this->payload(['additional_data' => '<p>Night shift</p>']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Omar Hauler')
            ->assertJsonPath('data.affiliation', 'contractor')
            ->assertJsonPath('data.roles.0', 'contractor_user')
            ->assertJsonPath('data.contractor.id', $this->desert->id)
            ->assertJsonPath('data.contractor.name', 'Desert Haulage')
            ->assertJsonPath('data.entity_name', 'Desert Haulage');

        $user = User::where('email', 'omar@desert.test')->firstOrFail();
        $this->assertTrue($user->isContractorUser());
        $this->assertSame($this->desert->id, $user->contractor_id);
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertTrue(Hash::check('Str0ng!Pass#1', $user->password));
        $this->assertSame($this->admin->id, $user->updated_by);
    }

    public function test_the_photo_is_stored_on_the_public_disk(): void
    {
        $id = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/contractor-users', $this->payload(['photo' => UploadedFile::fake()->image('p.jpg')]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        Storage::disk('public')->assertExists(User::findOrFail($id)->photo);
    }

    public function test_data_entry_can_create_one_too(): void
    {
        $this->actingAs($this->staff('data_entry'), 'web')
            ->postJson('/api/v1/contractor-users', $this->payload())
            ->assertCreated();
    }

    public function test_the_required_fields_are_enforced(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password', 'status', 'contractor_id']);
    }

    public function test_the_email_must_be_unique_across_the_system(): void
    {
        $this->createContractorUser();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', $this->payload(['phone' => '+966500000399']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_contractor_must_be_one_of_the_active_ones_of_the_tenant(): void
    {
        $this->desert->forceFill(['operational_status' => 'deactivated'])->save();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = Contractor::factory()->create(['prefix' => 'FOR']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractor-users', $this->payload(['contractor_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');
    }

    public function test_the_generic_users_endpoint_refuses_the_role(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'X', 'email' => 'x@x.test', 'phone' => '+966500000302', 'password' => 'Str0ng!Pass#1',
                'password_confirmation' => 'Str0ng!Pass#1', 'status' => 'active', 'roles' => ['contractor_user'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles.0');
    }

    public function test_only_admin_and_data_entry_may_create(): void
    {
        foreach (['auditor', 'driver', 'contractor_user'] as $role) {
            $this->actingAs($this->staff($role), 'web')
                ->postJson('/api/v1/contractor-users', $this->payload())
                ->assertForbidden();
        }
    }

    // -------------------------------------------------------------------- edit

    public function test_every_field_including_the_email_and_contractor_is_editable(): void
    {
        $user = $this->createContractorUser();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$user->id}", [
                'name' => 'Omar Renamed', 'email' => 'omar2@gulf.test', 'phone' => '+966500000333',
                'contractor_id' => $this->gulf->id, 'password' => 'N3w!Passw0rd#', 'password_confirmation' => 'N3w!Passw0rd#',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'omar2@gulf.test')
            ->assertJsonPath('data.contractor.id', $this->gulf->id);

        $fresh = $user->fresh();
        $this->assertSame('Omar Renamed', $fresh->name);
        $this->assertSame($this->gulf->id, $fresh->contractor_id);
        $this->assertTrue(Hash::check('N3w!Passw0rd#', $fresh->password));
        $this->assertTrue($fresh->isContractorUser());
    }

    public function test_a_blank_password_keeps_the_current_one(): void
    {
        $user = $this->createContractorUser();
        $hash = $user->password;

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$user->id}", ['name' => 'Same Pass', 'phone' => $user->phone, 'contractor_id' => $this->desert->id])
            ->assertOk();

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_the_current_contractor_stays_valid_after_it_was_deactivated(): void
    {
        $user = $this->createContractorUser();
        $this->desert->forceFill(['operational_status' => 'deactivated'])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$user->id}", ['name' => 'Still Here', 'phone' => $user->phone, 'contractor_id' => $this->desert->id])
            ->assertOk();
    }

    public function test_moving_to_a_deactivated_contractor_is_refused(): void
    {
        $user = $this->createContractorUser();
        $this->gulf->forceFill(['operational_status' => 'deactivated'])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$user->id}", ['name' => 'Move', 'phone' => $user->phone, 'contractor_id' => $this->gulf->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');
    }

    public function test_the_generic_edit_endpoint_refuses_a_contractor_user(): void
    {
        $user = $this->createContractorUser();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'Via generic', 'phone' => $user->phone, 'roles' => ['data_entry']])
            ->assertForbidden();

        $this->assertTrue($user->fresh()->isContractorUser());
    }

    public function test_the_contractor_endpoint_refuses_a_non_contractor_account(): void
    {
        $auditor = $this->staff('auditor');

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$auditor->id}", ['name' => 'X', 'phone' => '+966500000304', 'contractor_id' => $this->desert->id])
            ->assertForbidden();
    }

    public function test_the_auditor_cannot_edit(): void
    {
        $user = $this->contractorUser();

        $this->actingAs($this->staff('auditor'), 'web')
            ->patchJson("/api/v1/contractor-users/{$user->id}", ['name' => 'Nope', 'phone' => $user->phone, 'contractor_id' => $this->desert->id])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ list / status

    public function test_the_users_list_shows_it_filters_by_role_and_by_contractor(): void
    {
        $omar = $this->createContractorUser();
        $this->createContractorUser(['name' => 'Gulf Guy', 'email' => 'g@gulf.test', 'phone' => '+966500000305', 'contractor_id' => $this->gulf->id]);
        $this->staff('auditor');

        $byRole = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/users?role=contractor_user')->assertOk()->json('data'));
        $this->assertCount(2, $byRole);

        $byContractor = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/users?contractor_id='.$this->desert->id)->assertOk()->json('data'));
        $this->assertSame([$omar->id], $byContractor->pluck('id')->all());
        $this->assertSame('Desert Haulage', $byContractor->first()['entity_name']);
    }

    public function test_status_is_changed_through_the_shared_endpoint(): void
    {
        $user = $this->createContractorUser();

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'on_vacation'])->assertOk();
        $this->assertSame('on_vacation', $user->fresh()->status);
    }

    public function test_a_contractor_user_can_change_their_own_password_but_reach_no_admin_module(): void
    {
        $user = $this->contractorUser();

        $this->assertTrue($user->canChangeOwnPassword());

        // No admin module is reachable for the role.
        foreach (['/api/v1/users', '/api/v1/contractors', '/api/v1/vehicles', '/api/v1/drivers', '/api/v1/assets'] as $url) {
            $this->actingAs($user, 'web')->getJson($url)->assertForbidden();
        }
    }

    public function test_a_contractor_user_changes_their_own_password_and_photo_only(): void
    {
        $user = $this->contractorUser();

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', ['current_password' => 'password', 'new_password' => 'N3w!Passw0rd#', 'new_password_confirmation' => 'N3w!Passw0rd#'])
            ->assertNoContent();

        $this->assertTrue(Hash::check('N3w!Passw0rd#', $user->fresh()->password));
    }

    public function test_a_contractor_user_cannot_set_a_signature_or_stamp(): void
    {
        $user = $this->contractorUser();

        $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('s.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_a_contractor_user_cannot_write_to_any_admin_module(): void
    {
        $user = $this->contractorUser();

        foreach (['/api/v1/contractors', '/api/v1/client-users', '/api/v1/contractor-users', '/api/v1/users', '/api/v1/vehicles', '/api/v1/drivers'] as $url) {
            $this->actingAs($user, 'web')->postJson($url, [])->assertForbidden();
        }
    }

    public function test_a_contractor_user_is_offered_no_admin_menu_entries(): void
    {
        $this->withoutVite();

        $html = $this->actingAs($this->contractorUser(), 'web')->get('/dashboard')->assertOk()->getContent();

        foreach (['app/user/list', 'app/contractor/list', 'app/vehicle/list', 'app/driver/list', 'app/asset/list', 'app/company/list'] as $path) {
            $this->assertStringNotContainsString($path, $html);
        }
    }

    public function test_a_contractor_user_cannot_open_the_account_forms(): void
    {
        $user = $this->contractorUser();

        $this->actingAs($user, 'web')->get('/app/contractor-user/add')->assertForbidden();
        $this->actingAs($user, 'web')->get("/app/contractor-user/edit/{$user->id}")->assertForbidden();
        $this->actingAs($user, 'web')->get('/app/contractor/add')->assertForbidden();
    }

    // ---------------------------------------------------- representative account

    public function test_a_contractor_picks_one_of_its_active_users_as_representative(): void
    {
        $omar = $this->createContractorUser();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$this->desert->id}", ['name' => 'Desert Haulage', 'representative_id' => $omar->id])
            ->assertOk()
            ->assertJsonPath('data.representative.id', $omar->id)
            ->assertJsonPath('data.representative.name', 'Omar Hauler');

        $this->assertSame($omar->id, $this->desert->fresh()->representative_id);

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/contractors/{$this->desert->id}")
            ->assertJsonPath('data.representative.email', 'omar@desert.test');
    }

    public function test_the_representative_can_be_cleared(): void
    {
        $omar = $this->createContractorUser();
        $this->desert->forceFill(['representative_id' => $omar->id])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$this->desert->id}", ['name' => 'Desert Haulage', 'representative_id' => null])
            ->assertOk()
            ->assertJsonPath('data.representative', null);
    }

    public function test_an_edit_without_the_field_leaves_the_representative_alone(): void
    {
        $omar = $this->createContractorUser();
        $this->desert->forceFill(['representative_id' => $omar->id])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$this->desert->id}", ['name' => 'Renamed Contractor'])
            ->assertOk();

        $this->assertSame($omar->id, $this->desert->fresh()->representative_id);
    }

    public function test_only_an_active_user_of_this_contractor_qualifies(): void
    {
        $gulfUser = $this->createContractorUser(['email' => 'g@gulf.test', 'phone' => '+966500000306', 'contractor_id' => $this->gulf->id]);
        $inactive = $this->createContractorUser(['email' => 'v@desert.test', 'phone' => '+966500000307', 'status' => 'on_vacation']);
        $driver = User::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $this->desert->id]);
        $driver->assignRole('driver');

        foreach ([$gulfUser, $inactive, $driver, $this->admin] as $candidate) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/contractors/{$this->desert->id}", ['name' => 'Desert Haulage', 'representative_id' => $candidate->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('representative_id');
        }
    }

    public function test_an_unchanged_representative_stays_valid_after_going_on_vacation(): void
    {
        $omar = $this->createContractorUser();
        $this->desert->forceFill(['representative_id' => $omar->id])->save();
        $omar->forceFill(['status' => 'on_vacation'])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractors/{$this->desert->id}", ['name' => 'Edited', 'representative_id' => $omar->id])
            ->assertOk();
    }

    public function test_moving_the_user_to_another_contractor_releases_the_representation(): void
    {
        $omar = $this->createContractorUser();
        $this->desert->forceFill(['representative_id' => $omar->id])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/contractor-users/{$omar->id}", ['name' => 'Omar', 'phone' => $omar->phone, 'contractor_id' => $this->gulf->id])
            ->assertOk();

        $this->assertNull($this->desert->fresh()->representative_id);
    }

    public function test_the_representative_is_only_chosen_on_edit_not_on_create(): void
    {
        $omar = $this->createContractorUser();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/contractors', ['name' => 'New One', 'prefix' => 'NEW', 'operational_status' => 'active', 'representative_id' => $omar->id])
            ->assertCreated();

        $this->assertNull(Contractor::where('prefix', 'NEW')->firstOrFail()->representative_id);
    }

    // -------------------------------------------------------------------- pages

    public function test_the_form_pages_are_for_whoever_may_manage_accounts(): void
    {
        $this->withoutVite();
        $user = $this->contractorUser();

        $this->actingAs($this->admin, 'web')->get('/app/contractor-user/add')->assertOk();
        $this->actingAs($this->admin, 'web')->get("/app/contractor-user/edit/{$user->id}")->assertOk();
    }

    public function test_the_auditor_cannot_open_the_form_pages(): void
    {
        $user = $this->contractorUser();
        $auditor = $this->staff('auditor');

        $this->actingAs($auditor, 'web')->get('/app/contractor-user/add')->assertForbidden();
        $this->actingAs($auditor, 'web')->get("/app/contractor-user/edit/{$user->id}")->assertForbidden();
    }
}
