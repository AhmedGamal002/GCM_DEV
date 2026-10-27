<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * A new password must end the account's other ways in — an API token or a browser session that somebody else holds
 * stays valid for as long as nothing revokes it, which is exactly what changing the password is meant to cut off.
 */
class PasswordChangeRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        $this->victim = User::factory()->create(['email' => 'victim@gcm.test', 'password' => Hash::make('old-password-1')]);
        $this->victim->assignRole('auditor');
        $this->victim->forceFill(['remember_token' => 'old-remember-token'])->saveQuietly();

        // Two other devices: a bearer token and a database session row.
        $this->victim->createToken('stolen');
        $this->victim->createToken('phone');

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'someone-elses-browser', 'user_id' => $this->victim->id, 'ip_address' => '203.0.113.9',
            'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'an-unrelated-user', 'user_id' => $this->admin->id, 'ip_address' => '203.0.113.10',
            'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time(),
        ]);
    }

    private function assertVictimIsSignedOutEverywhere(): void
    {
        $this->assertSame(0, $this->victim->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'someone-elses-browser']);
        $this->assertNotSame('old-remember-token', $this->victim->fresh()->remember_token);

        // Nobody else is touched.
        $this->assertDatabaseHas('sessions', ['id' => 'an-unrelated-user']);
    }

    public function test_an_admin_setting_a_new_password_signs_the_account_out_everywhere(): void
    {
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/users/{$this->victim->id}", [
            'name' => 'Victim',
            'phone' => '0551234567',
            'roles' => ['auditor'],
            'password' => 'A-brand-new-password-2',
            'password_confirmation' => 'A-brand-new-password-2',
        ])->assertOk();

        $this->assertVictimIsSignedOutEverywhere();
    }

    public function test_a_password_reset_signs_the_account_out_everywhere(): void
    {
        $token = Password::broker('users')->createToken($this->victim);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'victim@gcm.test',
            'password' => 'A-brand-new-password-2',
            'password_confirmation' => 'A-brand-new-password-2',
        ])->assertOk();

        $this->assertVictimIsSignedOutEverywhere();
        $this->assertTrue(Hash::check('A-brand-new-password-2', $this->victim->fresh()->password));
    }

    public function test_changing_ones_own_password_signs_out_the_other_devices(): void
    {
        $this->actingAs($this->victim, 'web')->patchJson('/api/v1/me/password', [
            'current_password' => 'old-password-1',
            'new_password' => 'A-brand-new-password-2',
            'new_password_confirmation' => 'A-brand-new-password-2',
        ])->assertNoContent();

        $this->assertVictimIsSignedOutEverywhere();
    }

    public function test_an_edit_that_leaves_the_password_alone_keeps_everything(): void
    {
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/users/{$this->victim->id}", [
            'name' => 'Victim renamed',
            'phone' => '0551234567',
            'roles' => ['auditor'],
        ])->assertOk();

        $this->assertSame(2, $this->victim->tokens()->count());
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses-browser']);
        $this->assertSame('old-remember-token', $this->victim->fresh()->remember_token);
    }
}
