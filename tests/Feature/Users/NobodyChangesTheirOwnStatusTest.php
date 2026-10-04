<?php

namespace Tests\Feature\Users;

use App\Domain\Users\Actions\UpdateUserStatusAction;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An account's status (active / on vacation / deactivated) is decided by somebody else — whatever the
 * role, nobody can put themselves on vacation, deactivate or reactivate themselves.
 */
class NobodyChangesTheirOwnStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public static function statuses(): array
    {
        return [['on_vacation'], ['deactivated'], ['active']];
    }

    #[DataProvider('statuses')]
    public function test_the_system_admin_cannot_change_their_own_status(string $status): void
    {
        $admin = $this->user('system_admin');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/users/{$admin->id}/status", ['status' => $status])
            ->assertForbidden()
            ->assertJsonPath('message', __('You cannot change the status of your own account — ask the System Admin or Data Entry.'));

        $this->assertSame('active', $admin->fresh()->status);
    }

    #[DataProvider('statuses')]
    public function test_data_entry_cannot_change_their_own_status(string $status): void
    {
        $dataEntry = $this->user('data_entry');

        $this->actingAs($dataEntry, 'web')->patchJson("/api/v1/users/{$dataEntry->id}/status", ['status' => $status])->assertForbidden();

        $this->assertSame('active', $dataEntry->fresh()->status);
    }

    #[DataProvider('statuses')]
    public function test_nobody_else_can_either(string $status): void
    {
        foreach (['auditor', 'driver'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user, 'web')->patchJson("/api/v1/users/{$user->id}/status", ['status' => $status])->assertForbidden();

            $this->assertSame('active', $user->fresh()->status);
        }
    }

    public function test_another_account_can_still_change_it(): void
    {
        $dataEntry = $this->user('data_entry');
        $admin = $this->user('system_admin');

        $this->actingAs($admin, 'web')->patchJson("/api/v1/users/{$dataEntry->id}/status", ['status' => 'on_vacation'])->assertOk();

        $this->assertSame('on_vacation', $dataEntry->fresh()->status);
    }

    public function test_the_action_itself_refuses_a_self_change_whoever_calls_it(): void
    {
        $admin = $this->user('system_admin');

        $this->expectException(AuthorizationException::class);

        app(UpdateUserStatusAction::class)->execute($admin, 'on_vacation', $admin);
    }

    public function test_the_edit_form_of_your_own_account_hides_the_status_and_the_details_page_hides_the_card(): void
    {
        $this->withoutVite();
        $admin = $this->user('system_admin');

        $this->actingAs($admin, 'web')->get("/app/user/edit/{$admin->id}")->assertOk()->assertSee('userEditIsSelf = true', false);
        $this->actingAs($admin, 'web')->get("/app/user/view/{$admin->id}")->assertOk()->assertSee('gcmCanManage = false', false);
    }

    public function test_someone_elses_pages_keep_the_status_controls(): void
    {
        $this->withoutVite();
        $admin = $this->user('system_admin');
        $other = $this->user('auditor');

        $this->actingAs($admin, 'web')->get("/app/user/edit/{$other->id}")->assertOk()->assertSee('userEditIsSelf = false', false);
        $this->actingAs($admin, 'web')->get("/app/user/view/{$other->id}")->assertOk()->assertSee('gcmCanManage = true', false);
    }
}
