<?php

namespace App\Domain\Tenants\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Platform-only: provisions a brand new tenant organization plus its
 * first user — that tenant's sole system_admin (see
 * OnlyOneSystemAdminPerTenantRule for why a tenant can never have more
 * than one). This is a bootstrap flow distinct from the tenant-side
 * CreateUserAction (which an existing system_admin uses to add more
 * users to their own already-provisioned tenant).
 */
class CreateTenantAction
{
    /**
     * @param  array{name: string, slug: string, domain?: ?string, admin_name: string, admin_email: string, admin_phone: string, admin_password: string}  $data
     */
    public function execute(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?? null,
            'status' => 'active',
        ]);

        // BelongsToTenant's creating() hook stamps tenant_id from
        // app('tenant') — bind it for this one request so the first user
        // (and their auto-generated `code`) lands in the new tenant.
        app()->instance('tenant', $tenant);

        $admin = User::create([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'phone' => $data['admin_phone'],
            'password' => Hash::make($data['admin_password']),
            'status' => 'active',
            'affiliation' => 'gcm',
        ]);
        $admin->assignRole('system_admin');

        return $tenant;
    }
}
