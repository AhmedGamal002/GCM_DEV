<?php

namespace App\View\Composers;

use App\Models\User;
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
 * of the tenant menu (Users, Vehicles, and every other Vuexy demo item)
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

            $user = Auth::guard('web')->user();

            $vertical->menu = $this->filter($vertical->menu, $user);
            $horizontal->menu = $this->filter($horizontal->menu, $user);
        }

        $view->with('menuData', [$vertical, $horizontal]);
    }

    /**
     * Tenant-side menu, with any "platformOnly" node removed (Super-Admin
     * routes) and every other node gated by role:
     *  - a node carrying a "roles" allowlist is shown only to a user who
     *    actually holds one of those roles;
     *  - a node with NO "roles" key — every untouched Vuexy demo item
     *    (Layouts, Front Pages, Email/Chat/Kanban/eCommerce, Components,
     *    etc.) — is **hidden from everyone** by default, system_admin
     *    included: the sidebar shows only what has actually been built
     *    (client demos). Set SHOW_DEMO_MENU=true in .env (see
     *    config/custom.php) to bring the scaffold back for system_admin
     *    only while developing — it was system_admin's default view
     *    before that flag existed. Every role's menu otherwise contains
     *    only what it's actually permitted to use — see the real "Users"
     *    node bug this whole allowlist exists for, below.
     *
     * Real bug this fixes (originally): every logged-in tenant user —
     * including `driver`, who has zero API access to Users/Drivers/
     * Vehicles (see those Policies) — saw the exact same sidebar as
     * system_admin. Clicking any of those links loaded a real page whose
     * data fetch then silently 403'd into an empty-looking table (see
     * datatables-server-side.js's catch), not a clear "no access" state.
     * The demo-scaffold-defaults-to-admin-only rule above closes the same
     * gap for the rest of the menu (none of which is functional for a
     * non-admin role anyway).
     *
     * The "roles" key on a real node must mirror that page's actual
     * Policy::viewAny() roles — kept in sync by hand, there being no
     * single source of truth to derive it from automatically.
     *
     * A child does NOT inherit its parent's "roles" — a submenu item with
     * no "roles" key of its own is hidden (see above),
     * even under a parent open to other roles too. Not an issue for any
     * node today (every non-admin-only parent is currently a leaf, no
     * submenu), but a future "roles"-carrying parent with children needs
     * those children tagged explicitly too, or they'll silently vanish
     * for the exact roles the parent was just opened up to.
     */
    private function filter(array $items, ?User $user): array
    {
        $items = array_values(array_filter($items, function ($item) use ($user) {
            if (! empty($item->platformOnly)) {
                return false;
            }

            // A section header ("Accounts", "Fleet & Assets", ...) has no
            // roles of its own — whether it shows is decided below, by
            // whether anything under it survives for this user.
            if (isset($item->menuHeader)) {
                return true;
            }

            // No "roles" key = an untouched Vuexy demo item. Hidden from
            // everyone unless SHOW_DEMO_MENU is on (then system_admin only).
            $roles = $item->roles ?? (config('custom.custom.showDemoMenu') ? ['system_admin'] : []);

            return $user && $user->hasAnyRole($roles);
        }));

        foreach ($items as $item) {
            if (isset($item->submenu)) {
                $item->submenu = $this->filter($item->submenu, $user);
            }
        }

        return $this->dropEmptyHeaders($items);
    }

    /**
     * A header stays only if a real item follows it before the next header
     * (or the end) — so a role that can't see anything in a section never
     * sees a dangling section title, and a future module needs no
     * bookkeeping on its header.
     */
    private function dropEmptyHeaders(array $items): array
    {
        $kept = [];

        foreach ($items as $i => $item) {
            if (isset($item->menuHeader)) {
                $next = $items[$i + 1] ?? null;

                if ($next === null || isset($next->menuHeader)) {
                    continue;
                }
            }

            $kept[] = $item;
        }

        return $kept;
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
