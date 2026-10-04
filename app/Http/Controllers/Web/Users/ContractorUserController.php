<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;

/**
 * Blade shells only — contractor users (FRD V01.14 §1.4) are created and
 * edited through POST/PATCH /api/v1/contractor-users; the details page is the
 * shared /app/user/view/{id}. Authorization happens in the API calls, not
 * here (same as ClientUserController).
 */
class ContractorUserController extends Controller
{
    public function add()
    {
        return view('tenant.users.contractor-user-add');
    }

    public function edit(int $user)
    {
        return view('tenant.users.contractor-user-edit', ['userId' => $user]);
    }
}
