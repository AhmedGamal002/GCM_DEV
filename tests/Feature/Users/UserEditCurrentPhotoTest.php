<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user edit form shows the photo already on record next to its input (like the driver and vehicle
 * forms), so the person editing can open the current one and knows that leaving the input empty keeps it.
 * The link itself is filled in by app-user-edit.js from the API resource; this checks the slot and its
 * texts are on the page.
 */
class UserEditCurrentPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_edit_form_has_a_current_photo_slot(): void
    {
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->get('/app/user/edit/1')
            ->assertOk()
            ->assertSee('id="current-photo"', false)
            ->assertSee('Leave empty to keep the current file.');
    }
}
