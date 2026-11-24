<?php

namespace App\Providers;

use App\Auth\TenantUnawareEloquentUserProvider;
use App\Models\PersonalAccessToken;
use App\Support\SafeExcelValueBinder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    // One read of the tenant's expiry thresholds per request, however many documents a page lists.
    $this->app->singleton(\App\Domain\Settings\Support\ExpiryWarning::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // PDF exports use DejaVu Sans (the only bundled font with Arabic, see
    // ArabicText). Without subsetting DomPDF embeds the whole ~750KB font
    // file in every PDF, even a one-page one.
    config()->set('dompdf.options.enable_font_subsetting', true);

    // Excel exports must not turn user-typed "=..." text into formulas — see SafeExcelValueBinder.
    config()->set('excel.value_binder.default', SafeExcelValueBinder::class);

    // See TenantUnawareEloquentUserProvider's docblock: auth's own
    // retrieveById/retrieveByCredentials/retrieveByToken lookups must
    // bypass BelongsToTenant, since they run before any tenant is bound.
    Auth::provider('tenant_unaware_eloquent', function ($app, array $config) {
      return new TenantUnawareEloquentUserProvider($app['hash'], $config['model']);
    });

    // See PersonalAccessToken's docblock: the bearer-token (mobile) auth
    // path resolves its owner via this model's tokenable() relation,
    // completely outside the 'users' auth provider above — needs its
    // own BelongsToTenant bypass or every token-authenticated request 500s.
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

    // Brute-force protection on login: 5 attempts/minute per email+IP pair,
    // so a single attacker IP can't lock out a victim's account by hammering
    // just their email from other IPs, and vice versa.
    // The second limit is per IP alone: the email+IP pair above restarts its count with every new email address, so
    // one machine could otherwise try unlimited different accounts (credential stuffing).
    RateLimiter::for('login', function (Request $request) {
      $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

      return [
        Limit::perMinute(5)->by($key),
        Limit::perMinute(30)->by('ip|'.$request->ip()),
      ];
    });

    // The Super Admin login (guard `platform`, POST /platform/login) had no limit at all.
    RateLimiter::for('platform-login', function (Request $request) {
      $key = 'platform|'.Str::lower((string) $request->input('email')).'|'.$request->ip();

      return [
        Limit::perMinute(5)->by($key),
        Limit::perMinute(30)->by('platform-ip|'.$request->ip()),
      ];
    });

    // Same reasoning for password reset — prevents both mailbox-spamming
    // a victim's email and brute-forcing a reset token.
    RateLimiter::for('password-reset', function (Request $request) {
      $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

      return [
        Limit::perMinute(5)->by($key),
        Limit::perMinute(20)->by('reset-ip|'.$request->ip()),
      ];
    });

    // Every other API call (the group gets it from `throttleApi()` in bootstrap/app.php): a ceiling per signed-in user
    // — or per bearer token, per IP for a guest — so one account cannot scrape or hammer the whole API, plus a much
    // lower one for the Excel / PDF exports, which are the expensive requests.
    // E-mailed one-time codes (part 7.3): asking for one is capped per account + IP (and per IP), entering one too —
    // on top of the one-minute gap between codes and the 5 tries per code in OtpService.
    RateLimiter::for('otp-send', function (Request $request) {
      $who = 'u'.($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip();

      return [
        Limit::perHour((int) config('otp.send_per_hour'))->by('otp-send|'.$who),
        Limit::perMinute(20)->by('otp-send-ip|'.$request->ip()),
      ];
    });

    RateLimiter::for('otp-verify', function (Request $request) {
      $who = 'u'.($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip();

      return [
        Limit::perMinute(10)->by('otp-verify|'.$who),
        Limit::perMinute(30)->by('otp-verify-ip|'.$request->ip()),
      ];
    });

    RateLimiter::for('api', function (Request $request) {
      $token = $request->bearerToken();
      $who = $request->user()
        ? 'user:'.$request->user()->getAuthIdentifier()
        : ($token ? 'token:'.sha1($token) : 'ip:'.$request->ip());

      $limits = [Limit::perMinute((int) config('app.api_rate_limit', 300))->by($who)];

      // A trip document preview (8.1) is a PDF drawn on request too — as expensive as an export.
      if ($request->is('api/v1/*/export', 'api/v1/trips/*/documents/*')) {
        $limits[] = Limit::perMinute((int) config('app.export_rate_limit', 20))->by('export|'.$who);
      }

      return $limits;
    });

    Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
      if ($src !== null) {
        return [
          'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' :
                    (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
        ];
      }
      return [];
    });
  }
}