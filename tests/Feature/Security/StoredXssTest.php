<?php

namespace Tests\Feature\Security;

use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Additional Data" is a rich-text field that the view pages put into `innerHTML`. A data-entry account can post any
 * string straight to the API (the editor in the form is not a security boundary), so the value must be cleaned on the
 * way in AND on the way out — the system admin who later opens the record must never run what was typed.
 */
class StoredXssTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<p>keep me</p><img src=x onerror="fetch(\'/api/v1/users\')"><script>alert(1)</script>';

    private User $dataEntry;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create();
        $this->dataEntry->assignRole('data_entry');
    }

    private function assertClean(?string $value): void
    {
        $this->assertSame('<p>keep me</p>', $value);
    }

    public function test_markup_posted_to_the_api_is_cleaned_before_it_is_stored(): void
    {
        $response = $this->actingAs($this->dataEntry, 'web')->postJson('/api/v1/asset-capacity-categories', [
            'name' => 'Skip 20',
            'applies_to' => 'container',
            'capacity_cbm' => 20,
            'capacity_ton' => 8,
            'additional_data' => self::PAYLOAD,
        ]);

        $response->assertCreated();
        $this->assertClean($response->json('data.additional_data'));
        $this->assertClean(DB::table('asset_capacity_categories')->value('additional_data'));
    }

    public function test_a_row_that_already_holds_script_is_cleaned_when_it_is_read(): void
    {
        // Saved before the sanitizer existed (or by a console command): straight into the table, no model, no request.
        $category = AssetCapacityCategory::factory()->container()->create();
        DB::table('asset_capacity_categories')->where('id', $category->id)->update(['additional_data' => self::PAYLOAD]);

        $this->assertClean(
            $this->actingAs($this->admin, 'web')->getJson("/api/v1/asset-capacity-categories/{$category->id}")->json('data.additional_data')
        );
    }

    public function test_a_users_notes_are_cleaned_on_edit_and_on_read(): void
    {
        $target = User::factory()->create();
        $target->assignRole('auditor');

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Target',
            'phone' => '0551234567',
            'roles' => ['auditor'],
            'additional_data' => self::PAYLOAD,
        ])->assertOk();

        $this->assertClean(DB::table('users')->where('id', $target->id)->value('additional_data'));

        DB::table('users')->where('id', $target->id)->update(['additional_data' => self::PAYLOAD]);
        $this->assertClean(
            $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$target->id}")->json('data.additional_data')
        );
    }
}
