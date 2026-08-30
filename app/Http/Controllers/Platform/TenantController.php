<?php

namespace App\Http\Controllers\Platform;

use App\Concerns\BelongsToTenant;
use App\Domain\Tenants\Actions\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Classic session Blade controller — same reasoning as RoleController:
 * Platform has no mobile-app future, so no JSON API split here.
 *
 * Tenants are never hard-deleted (same rule as Users) — only suspended,
 * which EnsureTenant already enforces by force-logging-out and rejecting
 * every session belonging to a non-active tenant.
 */
class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::latest()->get();

        // Explicit per-tenant count, not withCount('users') — the users
        // relation's subquery still carries BelongsToTenant's fail-closed
        // scope (no tenant is bound in a Platform request), which throws
        // TenantContextMissingException. Same fix as RoleController's
        // users_count loop.
        foreach ($tenants as $tenant) {
            $tenant->users_count = User::withoutGlobalScope(BelongsToTenant::class)
                ->where('tenant_id', $tenant->id)
                ->count();
        }

        return view('platform.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('platform.tenants.create');
    }

    public function store(Request $request, CreateTenantAction $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['required', 'string', 'max:32'],
            'admin_password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $tenant = $action->execute($data);

        return redirect()->route('platform.tenants.index')
            ->with('status', "Tenant \"{$tenant->name}\" created, with {$data['admin_name']} as its system admin.");
    }

    public function edit(Tenant $tenant)
    {
        return view('platform.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update($data);

        return redirect()->route('platform.tenants.index')->with('status', "Tenant \"{$tenant->name}\" updated.");
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $tenant->update(['status' => $data['status']]);

        return redirect()->route('platform.tenants.index')
            ->with('status', "Tenant \"{$tenant->name}\" is now {$data['status']}.");
    }
}
