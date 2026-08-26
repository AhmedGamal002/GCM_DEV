<?php

namespace App\Providers;

use App\Auth\TenantUnawareEloquentUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // See TenantUnawareEloquentUserProvider's docblock: auth's own
    // retrieveById/retrieveByCredentials/retrieveByToken lookups must
    // bypass BelongsToTenant, since they run before any tenant is bound.
    Auth::provider('tenant_unaware_eloquent', function ($app, array $config) {
      return new TenantUnawareEloquentUserProvider($app['hash'], $config['model']);
    });

    // Brute-force protection on login: 5 attempts/minute per email+IP pair,
    // so a single attacker IP can't lock out a victim's account by hammering
    // just their email from other IPs, and vice versa.
    RateLimiter::for('login', function (Request $request) {
      $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

      return Limit::perMinute(5)->by($key);
    });

    // Same reasoning for password reset — prevents both mailbox-spamming
    // a victim's email and brute-forcing a reset token.
    RateLimiter::for('password-reset', function (Request $request) {
      $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

      return Limit::perMinute(5)->by($key);
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