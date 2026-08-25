<?php

namespace App\Providers;

use App\Auth\TenantUnawareEloquentUserProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;

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