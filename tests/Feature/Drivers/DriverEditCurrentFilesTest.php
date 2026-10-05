<?php

namespace Tests\Feature\Drivers;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The driver edit form shows the files already on record (photo + the four documents) next to their
 * inputs — like the vehicle edit form — so the person editing can open the current file and knows
 * that leaving the input empty keeps it. The links themselves are filled in by app-driver-edit.js
 * from the API resource; this checks the placeholders are on the page.
 */
class DriverEditCurrentFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edit_form_has_a_current_file_slot_for_the_photo_and_every_document(): void
    {
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $response = $this->actingAs($admin, 'web')->get('/app/driver/edit/1')->assertOk();

        foreach (['photo', 'residence', 'license', 'operational_license', 'insurance'] as $slot) {
            $response->assertSee('id="'.$slot.'_current"', false);
        }
    }
}
