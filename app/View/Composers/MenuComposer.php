<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

/**
 * Replaces MenuServiceProvider's old View::share('menuData', ...), which
 * ran during framework boot — before routing/auth middleware, so
 * Auth::guard() wasn't reliably resolved yet. A View Composer runs right
 * before the view renders (after all middleware), so auth state is
 * available here.
 *
 * A `platform`-guard session gets its own small, dedicated menu instead
 * of the tenant menu (Users, Fleet, and every other Vuexy demo item)
 * with just the "Roles & Permissions" node filtered in — those routes
 * all require the `web` guard/tenant context the Super Admin doesn't
 * have, so following any of them redirected to /login with no
 * explanation. See ARCHITECTURE.md §3.5.
 *
 * Deciding "is this the platform menu?" from `Auth::guard('platform')`
 * alone isn't enough: Laravel keeps every guard's login independently in
 * the same session, so a browser that's authenticated on *both* `web`
 * and `platform` (e.g. a Super Admin who also logged into a tenant
 * without logging out of Platform first — exactly what "session doesn't
 * belong to this tenant" style testing does) would show the Platform
 * menu even while viewing tenant pages. The current request's URL is the
 * actual signal for which area is being viewed.
 */
class MenuComposer
{
    public function compose(View $view): void
    {
        $isPlatform = Request::is('platform*') && Auth::guard('platform')->check();

        if ($isPlatform) {
            $menu = $this->platformMenu();
            $vertical = (object) ['menu' => $menu];
            $horizontal = (object) ['menu' => $menu];
        } else {
            $vertical = json_decode(file_get_contents(base_path('resources/menu/verticalMenu.json')));
            $horizontal = json_decode(file_get_contents(base_path('resources/menu/horizontalMenu.json')));

            $vertical->menu = $this->filter($vertical->menu);
            $horizontal->menu = $this->filter($horizontal->menu);
        }

        $view->with('menuData', [$vertical, $horizontal]);
    }

    /**
     * Tenant-side menu, with any "platformOnly" node (and its children,
     * recursively) removed — those are Super-Admin-only routes.
     */
    private function filter(array $items): array
    {
        $items = array_values(array_filter($items, fn ($item) => empty($item->platformOnly)));

        foreach ($items as $item) {
            if (isset($item->submenu)) {
                $item->submenu = $this->filter($item->submenu);
            }
        }

        return $items;
    }

    /**
     * @return array<int, object>
     */
    private function platformMenu(): array
    {
        return [
            (object) [
                'url' => 'platform/dashboard',
                'icon' => 'menu-icon tf-icons ti ti-smart-home',
                'name' => 'Dashboard',
                'slug' => 'platform-dashboard',
            ],
            (object) [
                'url' => 'platform/tenants',
                'icon' => 'menu-icon tf-icons ti ti-building-skyscraper',
                'name' => 'Tenants',
                'slug' => 'platform-tenants',
            ],
            (object) [
                'name' => 'Roles & Permissions',
                'icon' => 'menu-icon tf-icons ti ti-settings',
                'slug' => 'app-access',
                'submenu' => [
                    (object) ['url' => 'platform/roles', 'name' => 'Roles', 'slug' => 'platform-roles'],
                    (object) ['url' => 'platform/permissions', 'name' => 'Permission', 'slug' => 'platform-permissions'],
                ],
            ],
        ];
    }
}
