<?php

namespace App\Providers;

use App\View\Composers\MenuComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   *
   * Menu data is composed per-render (not shared at boot) because it
   * needs Auth state to filter platform-only entries — see
   * MenuComposer's docblock for why View::share() couldn't do this.
   */
  public function boot(): void
  {
    View::composer(
      ['layouts.sections.menu.verticalMenu', 'layouts.sections.menu.horizontalMenu'],
      MenuComposer::class
    );
  }
}
