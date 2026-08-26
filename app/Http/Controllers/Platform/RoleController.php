<?php

namespace App\Http\Controllers\Platform;

use App\Concerns\BelongsToTenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Classic session Blade controller — matches the existing Platform
 * pattern (AuthenticatedSessionController/DashboardController), not a
 * JSON API. Platform has no mobile-app future the way tenant auth does,
 * so there's no benefit to an axios/JSON split here — see the plan.
 *
 * Role/Permission CRUD is entirely Super Admin-only (guard `platform`,
 * see routes/platform.php) — see ARCHITECTURE.md §3.5.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::where('guard_name', 'web')->get();

        // Explicit per-role count, deliberately cross-tenant (Platform
        // manages roles across every tenant) — same reasoning as
        // destroy() below. Counted this way (rather than
        // withCount(['users' => ...])) because Eloquent's count
        // subquery doesn't reliably carry a closure-applied
        // withoutGlobalScope() through to the generated SQL.
        foreach ($roles as $role) {
            $role->users_count = User::withoutGlobalScope(BelongsToTenant::class)
                ->role($role->name)
                ->count();
        }

        return view('platform.roles.index', [
            'roles' => $roles,
            'permissions' => Permission::where('guard_name', 'web')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('platform.roles.index')->with('status', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role)
    {
        return view('platform.roles.edit', [
            'role' => $role,
            'permissions' => Permission::where('guard_name', 'web')->get(),
            'assignedPermissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('platform.roles.index')->with('status', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Platform manages roles across every tenant, so this check is
        // deliberately cross-tenant — the one legitimate use of the
        // BelongsToTenant escape hatch documented on the trait itself.
        if ($role->users()->withoutGlobalScope(BelongsToTenant::class)->exists()) {
            return back()->withErrors(['role' => "Cannot delete \"{$role->name}\" — it is still assigned to users."]);
        }

        $role->delete();

        return redirect()->route('platform.roles.index')->with('status', 'Role deleted.');
    }
}
