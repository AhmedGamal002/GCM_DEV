<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->user = User::factory()->create(['password' => bcrypt('old-password')]);
        $this->user->assignRole('driver');
    }

    public function test_password_changes_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'old-password',
                'new_password' => 'brand-new-password',
                'new_password_confirmation' => 'brand-new-password',
            ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('brand-new-password', $this->user->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'totally-wrong',
                'new_password' => 'brand-new-password',
                'new_password_confirmation' => 'brand-new-password',
            ]);

        $response->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_password_change_rejected_when_confirmation_does_not_match(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'old-password',
                'new_password' => 'brand-new-password',
                'new_password_confirmation' => 'does-not-match',
            ]);

        $response->assertJsonValidationErrors('new_password');
    }
}
