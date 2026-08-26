<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;

/**
 * Read-only for tenant users — e.g. populating a role-assignment
 * dropdown. Role/Permission CRUD is Super Admin-only, entirely under
 * routes/platform.php — see ARCHITECTURE.md §3.5.
 */
class RoleController extends Controller
{
    public function index()
    {
        return response()->json(
            Role::where('guard_name', 'web')->pluck('name')
        );
    }
}
