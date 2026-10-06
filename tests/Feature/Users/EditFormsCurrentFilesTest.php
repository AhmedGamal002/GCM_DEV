<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every edit form that has a file input shows the file already on record next to it (like the
 * vehicle and driver forms). The links are filled in by JS from the API resource; this checks the
 * placeholders are on the page for the user and client-account forms.
 */
class EditFormsCurrentFilesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        return $admin;
    }

    public function test_the_user_edit_form_has_a_current_photo_slot(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/app/user/edit/1')
            ->assertOk()
            ->assertSee('id="current-photo"', false);
    }

    public function test_the_client_account_edit_form_has_slots_for_photo_signature_and_stamp(): void
    {
        $response = $this->actingAs($this->admin(), 'web')->get('/app/client-user/edit/1')->assertOk();

        foreach (['photo', 'signature', 'stamp'] as $slot) {
            $response->assertSee('id="current-'.$slot.'"', false);
        }
    }
}
