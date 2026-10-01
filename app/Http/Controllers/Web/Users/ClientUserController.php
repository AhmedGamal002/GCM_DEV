<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;

/**
 * Blade shells only — client accounts (FRD V01.14 §1.4) are created and
 * edited through POST/PATCH /api/v1/client-users; the details page is the
 * shared /app/user/view/{id}. Authorization happens in the API calls, not
 * here (same as UserAccountController).
 */
class ClientUserController extends Controller
{
    public function add()
    {
        return view('tenant.users.client-add');
    }

    public function edit(int $user)
    {
        return view('tenant.users.client-edit', ['userId' => $user]);
    }
}
