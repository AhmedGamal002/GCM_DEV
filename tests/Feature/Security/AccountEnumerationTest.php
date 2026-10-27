<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The login and the "forgot password" form must not tell a stranger which email addresses have an account.
 */
class AccountEnumerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    public function test_forgot_password_answers_the_same_for_known_and_unknown_addresses(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@gcm.test']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@gcm.test']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@gcm.test']);

        $this->assertSame(200, $known->status());
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());

        // …while a reset link really goes out only for the real account.
        Notification::assertSentToTimes(User::where('email', 'known@gcm.test')->first(), \Illuminate\Auth\Notifications\ResetPassword::class, 1);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@gcm.test']);
    }

    public function test_the_per_user_reset_throttle_is_not_visible_either(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@gcm.test']);

        // The broker refuses a second link within 60 seconds for a real account — that must look like any other answer.
        $first = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@gcm.test']);
        $second = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@gcm.test']);

        $this->assertSame(200, $second->status());
        $this->assertSame($first->json(), $second->json());
    }

    public function test_login_gives_one_message_for_a_wrong_password_and_an_unknown_email(): void
    {
        User::factory()->create(['email' => 'known@gcm.test', 'password' => Hash::make('right-password')]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'known@gcm.test', 'password' => 'wrong-password']);
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'nobody@gcm.test', 'password' => 'wrong-password']);

        $wrongPassword->assertStatus(422);
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_the_right_password_still_logs_in(): void
    {
        User::factory()->create(['email' => 'known@gcm.test', 'password' => Hash::make('right-password')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'known@gcm.test', 'password' => 'right-password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }
}
