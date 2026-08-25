<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
    }

    #[DataProvider('gcmRolesProvider')]
    public function test_each_gcm_role_can_login(string $role): void
    {
        $user = User::factory()->create(['email' => "{$role}@gcm.test", 'password' => bcrypt('secret-password')]);
        $user->assignRole($role);

        $response = $this->post('/login', [
            'email' => "{$role}@gcm.test",
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertTrue(Auth::guard('web')->user()->is($user));
    }

    public static function gcmRolesProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
            ['auditor'],
            ['driver'],
        ];
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@gcm.test', 'password' => bcrypt('secret-password')]);

        $response = $this->post('/login', [
            'email' => 'admin@gcm.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'deactivated@gcm.test',
            'password' => bcrypt('secret-password'),
            'status' => 'deactivated',
        ]);

        $response = $this->post('/login', [
            'email' => 'deactivated@gcm.test',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('web')->check());
    }
}
