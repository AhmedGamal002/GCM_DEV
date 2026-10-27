<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Application Name
  |--------------------------------------------------------------------------
  |
  | This value is the name of your application, which will be used when the
  | framework needs to place the application's name in a notification or
  | other UI elements where an application name needs to be displayed.
  |
  */

  'name' => env('APP_NAME', 'Laravel'),

  /*
  |--------------------------------------------------------------------------
  | Application Environment
  |--------------------------------------------------------------------------
  |
  | This value determines the "environment" your application is currently
  | running in. This may determine how you prefer to configure various
  | services the application utilizes. Set this in your ".env" file.
  |
  */

  'env' => env('APP_ENV', 'production'),

  /*
  |--------------------------------------------------------------------------
  | Application Debug Mode
  |--------------------------------------------------------------------------
  |
  | When your application is in debug mode, detailed error messages with
  | stack traces will be shown on every error that occurs within your
  | application. If disabled, a simple generic error page is shown.
  |
  */

  'debug' => (bool) env('APP_DEBUG', false),

  /*
  |--------------------------------------------------------------------------
  | Application URL
  |--------------------------------------------------------------------------
  |
  | This URL is used by the console to properly generate URLs when using
  | the Artisan command line tool. You should set this to the root of
  | the application so that it's available within Artisan commands.
  |
  */

  'url' => env('APP_URL', 'http://localhost'),

  /*
  |--------------------------------------------------------------------------
  | Trusted Hosts
  |--------------------------------------------------------------------------
  |
  | The Host header is client-controlled, and Laravel builds absolute URLs from it — including the link in a
  | password-reset email. Outside local development (see bootstrap/app.php) only APP_URL's host, its subdomains and
  | the hosts listed here are accepted; any other Host gets a 400. List a tenant's own domain here (comma separated,
  | exact host names) when a tenant is served from a domain that is not under APP_URL.
  |
  */

  'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))))),

  /*
  |--------------------------------------------------------------------------
  | API Rate Limits (requests per minute)
  |--------------------------------------------------------------------------
  |
  | Per signed-in user (see the `api` limiter in AppServiceProvider). A page load makes a handful of calls, so the
  | default is far above normal use; raise it with API_RATE_LIMIT for a load test. Exports are limited separately.
  |
  */

  'api_rate_limit' => (int) env('API_RATE_LIMIT', 300),

  'export_rate_limit' => (int) env('EXPORT_RATE_LIMIT', 20),

  /*
  |--------------------------------------------------------------------------
  | Application Timezone
  |--------------------------------------------------------------------------
  |
  | Here you may specify the default timezone for your application, which
  | will be used by the PHP date and date-time functions. The timezone
  | is set to "UTC" by default as it is suitable for most use cases.
  |
  */

  'timezone' => env('APP_TIMEZONE', 'UTC'),

  /*
  |--------------------------------------------------------------------------
  | Application Locale Configuration
  |--------------------------------------------------------------------------
  |
  | The application locale determines the default locale that will be used
  | by Laravel's translation / localization methods. This option can be
  | set to any locale for which you plan to have translation strings.
  |
  */

  'locale' => env('APP_LOCALE', 'en'),

  'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

  'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

  /*
  |--------------------------------------------------------------------------
  | Encryption Key
  |--------------------------------------------------------------------------
  |
  | This key is utilized by Laravel's encryption services and should be set
  | to a random, 32 character string to ensure that all encrypted values
  | are secure. You should do this prior to deploying the application.
  |
  */

  'cipher' => 'AES-256-CBC',

  'key' => env('APP_KEY'),

  'previous_keys' => [
    ...array_filter(
      explode(',', env('APP_PREVIOUS_KEYS', ''))
    ),
  ],

  /*
  |--------------------------------------------------------------------------
  | Maintenance Mode Driver
  |--------------------------------------------------------------------------
  |
  | These configuration options determine the driver used to determine and
  | manage Laravel's "maintenance mode" status. The "cache" driver will
  | allow maintenance mode to be controlled across multiple machines.
  |
  | Supported drivers: "file", "cache"
  |
  */

  'maintenance' => [
    'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    'store' => env('APP_MAINTENANCE_STORE', 'database'),
  ],



  'aliases' => [
    // Other aliases...
    'Helper' => App\Helpers\Helpers::class,
  ],

];