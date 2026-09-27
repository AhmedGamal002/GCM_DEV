<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

/**
 * Same rule as UserPolicy — driver management (tenant side) is
 * (system_admin / data_entry), same FRD line repeated verbatim in the
 * driver section ("جميع البيانات قابلة للتعديل من خلال مدير النظام او
 * مدخل البيانات فقط"). auditor gets view/export only (V01.14, new —
 * see UserPolicy's docblock). A driver is still a User under the hood,
 * so this mirrors UserPolicy's shape exactly — including status changes,
 * which go through PATCH /api/v1/users/{id}/status
 * (UpdateUserStatusAction), not a method here.
 */
class DriverPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, Driver $target): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function update(User $actor, Driver $target): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
