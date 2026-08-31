<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's "صفحة عرض تفاصيل حساب" / "صفحة تعديل
 * الحساب" — a dedicated read-only details page and a dedicated edit page,
 * replacing the list page's old inline offcanvas. Both fetch/save their
 * data client-side against /api/v1/users/{id} — the {user} route param
 * isn't route-model-bound (same SubstituteBindings-before-tenant-
 * middleware reason as UserController), so authorization happens entirely
 * through the API call, not here.
 */
class UserAccountController extends Controller
{
    public function view(int $user)
    {
        return view('tenant.users.view', ['userId' => $user]);
    }

    public function edit(int $user)
    {
        return view('tenant.users.edit', ['userId' => $user]);
    }
}
