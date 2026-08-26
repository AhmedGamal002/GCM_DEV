<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Replaces MenuServiceProvider's old View::share('menuData', ...), which
 * ran during framework boot — before routing/auth middleware, so
 * Auth::guard() wasn't reliably resolved yet. A View Composer runs right
 * before the view renders (after all middleware), so auth state is
 * available here.
 *
 * Filters out any menu node marked "platformOnly": true unless the
 * current request is authenticated on the `platform` guard — see
 * ARCHITECTURE.md §3.5 ("Roles & Permissions" is Super Admin-only, not
 * visible to any tenant role including system_admin).
 */
class MenuComposer
{
    public function compose(View $view): void
    {
        $vertical = json_decode(file_get_contents(base_path('resources/menu/verticalMenu.json')));
        $horizontal = json_decode(file_get_contents(base_path('resources/menu/horizontalMenu.json')));

        $isPlatform = Auth::guard('platform')->check();

        $vertical->menu = $this->filter($vertical->menu, $isPlatform);
        $horizontal->menu = $this->filter($horizontal->menu, $isPlatform);

        $view->with('menuData', [$vertical, $horizontal]);
    }

    private function filter(array $items, bool $isPlatform): array
    {
        $items = array_values(array_filter($items, function ($item) use ($isPlatform) {
            return $isPlatform || empty($item->platformOnly);
        }));

        foreach ($items as $item) {
            if (isset($item->submenu)) {
                $item->submenu = $this->filter($item->submenu, $isPlatform);
            }
        }

        return $items;
    }
}
