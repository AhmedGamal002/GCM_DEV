<?php

namespace Tests\Feature\Otp;

use App\Domain\Otp\Mail\OtpCodeMail;
use App\Domain\Otp\OtpScope;
use App\Models\OtpChallenge;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Part 7.3 — the e-mailed one-time code (FRD V01.14 "ايميل OTP", decisions ج15–ج16): asking for a code, entering
 * it, the guard (`otp` middleware) that sits in front of the sensitive actions, "once per session", expiry, tries,
 * the one-minute gap, throttles, who may use it, and that only a hash of the code is kept.
 *
 * "Today" is frozen at 2026-03-01 10:00, so expiry is tested by travelling.
 */
class OtpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 3, 1)->setTime(10, 0));
        $this->seed(RoleSeeder::class);
        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
        Mail::fake();

        // A stand-in for a sensitive action: only the `otp` guard is under test here.
        Route::middleware(['api', 'auth:sanctum', 'tenant', 'otp'])->post('/api/v1/_otp-probe', fn () => response()->json(['done' => true]));
    }

    private function user(string $role = 'system_admin', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /** The code that was e-mailed last (Mail is faked, so it is read from the mailable). */
    private function lastCode(): string
    {
        $sent = Mail::sent(OtpCodeMail::class);
        $this->assertNotEmpty($sent, 'no code was e-mailed');

        return $sent->last()->code;
    }

    private function send(User $user, ?string $purpose = null)
    {
        return $this->actingAs($user, 'web')->postJson('/api/v1/otp/send', $purpose ? ['purpose' => $purpose] : []);
    }

    private function verify(User $user, string $code)
    {
        return $this->actingAs($user, 'web')->postJson('/api/v1/otp/verify', ['code' => $code]);
    }

    // ── sending ─────────────────────────────────────────────────────

    public function test_asking_for_a_code_e_mails_six_digits_to_the_accounts_own_address(): void
    {
        $user = $this->user(attributes: ['email' => 'maloum@gcm.test', 'name' => 'Maloum']);

        $this->send($user, 'Cancel trip ALN-T0001')
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'm****@gcm.test')
            ->assertJsonPath('data.expires_in_seconds', 600)
            ->assertJsonPath('data.resend_after_seconds', 60)
            ->assertJsonPath('data.code_length', 6);

        Mail::assertSent(OtpCodeMail::class, fn (OtpCodeMail $m) => $m->hasTo('maloum@gcm.test') && $m->purpose === 'Cancel trip ALN-T0001' && $m->ttlMinutes === 10);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastCode());
    }

    public function test_only_a_hash_of_the_code_is_kept(): void
    {
        $user = $this->user();
        $this->send($user)->assertOk();

        $code = $this->lastCode();
        $row = OtpChallenge::firstOrFail();

        $this->assertNotSame($code, $row->code_hash);
        $this->assertSame(64, strlen($row->code_hash));
        $this->assertStringNotContainsString($code, json_encode($row->getAttributes()));
    }

    public function test_the_mail_is_readable_in_both_languages_and_carries_the_code(): void
    {
        $user = $this->user(attributes: ['name' => 'Sara']);

        $en = (new OtpCodeMail($user, '123456', 10))->render();
        $this->assertStringContainsString('123456', $en);
        $this->assertStringContainsString('GLOBAL CLEAR MISSION', $en);
        $this->assertStringContainsString('dir="ltr"', $en);
        $this->assertStringContainsString('10 minutes', $en);

        app()->setLocale('ar');
        $ar = (new OtpCodeMail($user, '123456', 10))->render();
        $this->assertStringContainsString('123456', $ar);
        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString('رمز', $ar);
        app()->setLocale('en');
    }

    #[DataProvider('allowedRoles')]
    public function test_the_roles_the_fRD_makes_confirm_with_a_code_may_ask_for_one(string $role): void
    {
        $user = in_array($role, ['client_project_manager', 'client_project_auditor'], true)
            ? User::factory()->client(null, $role)->create()
            : $this->user($role);

        $this->send($user)->assertOk();
    }

    public static function allowedRoles(): array
    {
        return [['system_admin'], ['data_entry'], ['client_project_manager'], ['client_project_auditor']];
    }

    #[DataProvider('refusedRoles')]
    public function test_every_other_role_is_refused(string $role): void
    {
        $user = $this->user($role);

        $this->send($user)->assertForbidden();
        $this->verify($user, '123456')->assertForbidden();
        $this->actingAs($user, 'web')->getJson('/api/v1/otp/status')->assertForbidden();
        Mail::assertNothingSent();
    }

    public static function refusedRoles(): array
    {
        return [['auditor'], ['driver']];
    }

    public function test_a_guest_cannot_ask(): void
    {
        $this->postJson('/api/v1/otp/send')->assertUnauthorized();
        $this->postJson('/api/v1/otp/verify', ['code' => '123456'])->assertUnauthorized();
    }

    public function test_when_the_mail_cannot_be_sent_the_answer_says_so_and_no_code_is_left_waiting(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp is down'));
        $user = $this->user();

        $this->send($user)->assertStatus(503)->assertJsonPath('code', 'otp_mail_failed');

        $this->assertSame(0, OtpChallenge::count());
    }

    // ── entering the code ───────────────────────────────────────────

    public function test_the_right_code_verifies_the_login(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'web')->getJson('/api/v1/otp/status')->assertJsonPath('data.verified', false);

        $this->send($user);
        $this->verify($user, $this->lastCode())->assertOk()->assertJsonPath('data.verified', true);

        $this->actingAs($user, 'web')->getJson('/api/v1/otp/status')->assertJsonPath('data.verified', true);
    }

    public function test_a_code_is_good_once(): void
    {
        $user = $this->user();
        $this->send($user);
        $code = $this->lastCode();

        $this->verify($user, $code)->assertOk();
        // the row is the proof of verification; entering the same code again finds nothing open
        $this->verify($user, $code)->assertUnprocessable()->assertJsonPath('code', 'otp_not_sent');
    }

    public function test_a_wrong_code_spends_a_try_and_says_how_many_are_left(): void
    {
        $user = $this->user();
        $this->send($user);
        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';

        $this->verify($user, $wrong)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid')
            ->assertJsonPath('attempts_left', 4)
            ->assertJsonValidationErrors('code');

        $this->assertSame(1, OtpChallenge::firstOrFail()->attempts);
    }

    public function test_five_wrong_tries_kill_the_code_even_for_the_right_one(): void
    {
        $user = $this->user();
        $this->send($user);
        $right = $this->lastCode();
        $wrong = $right === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->verify($user, $wrong)->assertJsonPath('code', 'otp_invalid');
        }
        $this->verify($user, $wrong)->assertStatus(429)->assertJsonPath('code', 'otp_locked');

        // the code is dead: the right one no longer opens anything, a new one has to be asked for
        $this->verify($user, $right)->assertUnprocessable()->assertJsonPath('code', 'otp_not_sent');
        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertForbidden();
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $user = $this->user();
        $this->send($user);
        $code = $this->lastCode();

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->verify($user, $code)->assertUnprocessable()->assertJsonPath('code', 'otp_expired');
    }

    public function test_a_code_still_works_just_before_it_expires(): void
    {
        $user = $this->user();
        $this->send($user);
        $code = $this->lastCode();

        $this->travel(9)->minutes();

        $this->verify($user, $code)->assertOk();
    }

    public function test_entering_a_code_when_none_was_asked_for_says_so(): void
    {
        $this->verify($this->user(), '123456')->assertUnprocessable()->assertJsonPath('code', 'otp_not_sent');
    }

    public function test_the_code_is_required(): void
    {
        $this->actingAs($this->user(), 'web')->postJson('/api/v1/otp/verify', [])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_surrounding_spaces_in_the_pasted_code_do_not_matter(): void
    {
        $user = $this->user();
        $this->send($user);

        $this->verify($user, '  '.$this->lastCode().' ')->assertOk();
    }

    // ── asking again ────────────────────────────────────────────────

    public function test_a_new_code_cannot_be_asked_for_within_a_minute(): void
    {
        $user = $this->user();
        $this->send($user)->assertOk();

        $this->travel(30)->seconds();
        $this->send($user)->assertStatus(429)->assertJsonPath('code', 'otp_resend_too_soon')->assertJsonPath('retry_after', 30)->assertHeader('Retry-After', '30');
        Mail::assertSent(OtpCodeMail::class, 1);
    }

    public function test_after_a_minute_a_new_code_replaces_the_old_one(): void
    {
        $user = $this->user();
        $this->send($user);
        $old = $this->lastCode();

        $this->travel(61)->seconds();
        $this->send($user)->assertOk();
        $new = $this->lastCode();
        $this->assertNotSame($old, $new);

        $this->verify($user, $old)->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
        $this->verify($user, $new)->assertOk();
        $this->assertSame(1, OtpChallenge::whereNull('invalidated_at')->whereNotNull('verified_at')->count());
    }

    // ── the guard ───────────────────────────────────────────────────

    public function test_the_guard_refuses_until_the_code_is_entered_then_lets_the_action_through(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')
            ->assertForbidden()
            ->assertJsonPath('code', 'otp_required')
            ->assertJsonPath('message', 'Enter the verification code we e-mailed you to continue.');

        $this->send($user);
        // asking is not enough
        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertForbidden();

        $this->verify($user, $this->lastCode())->assertOk();

        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertOk()->assertJsonPath('done', true);
        // and stays verified for every later sensitive action
        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertOk();
    }

    public function test_the_verification_of_one_account_does_not_verify_another(): void
    {
        // account A has been verified in this kind of login (here: written straight to the table)…
        $a = $this->user(attributes: ['email' => 'a@gcm.test']);
        OtpChallenge::create([
            'user_id' => $a->id,
            'scope' => 'user:'.$a->id,
            'code_hash' => str_repeat('a', 64),
            'attempts' => 0,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        // …account B, acting alone (one actor per test: the sanctum guard caches the first), has not
        $b = $this->user('data_entry', ['email' => 'b@gcm.test']);
        $this->actingAs($b, 'web')->postJson('/api/v1/_otp-probe')->assertForbidden()->assertJsonPath('code', 'otp_required');
    }

    public function test_the_verification_lasts_a_working_day_not_forever(): void
    {
        $user = $this->user();
        $this->send($user);
        $this->verify($user, $this->lastCode())->assertOk();

        $this->travel(479)->minutes();
        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertOk();

        $this->travel(2)->minutes();
        $this->actingAs($user, 'web')->postJson('/api/v1/_otp-probe')->assertForbidden()->assertJsonPath('code', 'otp_required');
    }

    // ── once per session / per token ────────────────────────────────

    public function test_a_real_api_token_can_go_through_the_whole_flow(): void
    {
        $user = $this->user();
        $token = $user->createToken('postman')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/_otp-probe')->assertForbidden()->assertJsonPath('code', 'otp_required');
        $this->withToken($token)->postJson('/api/v1/otp/send')->assertOk();
        $this->withToken($token)->postJson('/api/v1/otp/verify', ['code' => $this->lastCode()])->assertOk();
        $this->withToken($token)->postJson('/api/v1/_otp-probe')->assertOk();
        $this->assertStringStartsWith('token:', OtpChallenge::firstOrFail()->scope);
    }

    public function test_each_api_token_is_its_own_session(): void
    {
        $user = $this->user();
        $first = $user->createToken('postman');
        $second = $user->createToken('postman-2')->plainTextToken;

        // the first token's login is verified…
        OtpChallenge::create([
            'user_id' => $user->id,
            'scope' => 'token:'.$first->accessToken->getKey(),
            'code_hash' => str_repeat('a', 64),
            'attempts' => 0,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        // …the other token of the same account has not entered a code
        $this->withToken($second)->postJson('/api/v1/_otp-probe')->assertForbidden()->assertJsonPath('code', 'otp_required');
    }

    public function test_the_scope_is_the_token_or_the_hashed_session_never_the_raw_session_id(): void
    {
        $user = $this->user();

        // a request with a session
        $request = Request::create('/api/v1/otp/send', 'POST');
        $request->setLaravelSession($store = app('session.store'));
        $request->setUserResolver(fn () => $user);
        $scope = OtpScope::of($request);

        $this->assertSame('session:'.hash('sha256', $store->getId()), $scope);
        $this->assertStringNotContainsString($store->getId(), $scope);

        // a different session is a different scope
        $other = Request::create('/api/v1/otp/send', 'POST');
        $other->setLaravelSession(tap(app('session')->driver(), fn ($s) => $s->setId(str_repeat('b', 40))));
        $other->setUserResolver(fn () => $user);
        $this->assertNotSame($scope, OtpScope::of($other));
    }

    // ── throttles ───────────────────────────────────────────────────

    public function test_asking_is_capped_per_hour_per_account(): void
    {
        config(['otp.send_per_hour' => 2]);
        $user = $this->user();

        $this->send($user)->assertOk();
        $this->travel(61)->seconds();
        $this->send($user)->assertOk();
        $this->travel(61)->seconds();
        $this->send($user)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_entering_codes_is_throttled_too(): void
    {
        $user = $this->user();
        $this->send($user);

        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->verify($user, '000001')->status();
        }

        // 422 / 429 from the code's own try limit, then the 10 per minute throttle: always a refusal, never a 200
        $this->assertNotContains(200, $statuses);
        $this->assertContains(429, $statuses);
    }

    // ── tenants ─────────────────────────────────────────────────────

    public function test_the_codes_of_one_tenant_are_invisible_to_another(): void
    {
        $user = $this->user();
        $this->send($user);

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);

        $this->assertSame(0, OtpChallenge::count());
    }
}
