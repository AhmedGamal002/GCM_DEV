<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\Actions\CreateUserAction;
use App\Domain\Users\Actions\UpdateUserAction;
use App\Domain\Users\Actions\UpdateUserStatusAction;
use App\Domain\Users\Exceptions\CannotDeactivateSystemAdminException;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Deliberately NOT implicit route-model binding on every {user} route:
 * Laravel's SubstituteBindings middleware runs as part of the global
 * 'api' middleware group, before this route's own 'tenant' middleware
 * gets a chance to bind app('tenant') — so an implicit User $user
 * parameter would throw TenantContextMissingException on every request,
 * even for a user's own tenant. Looking the model up explicitly inside
 * each action body runs after all middleware, once the tenant is bound.
 *
 * No hard-delete route exists here at all — see ARCHITECTURE.md's "no
 * hard delete" rule. Deactivation goes through `status()` instead, which
 * itself refuses to deactivate a system_admin (UpdateUserStatusAction).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('tenant')
            // This is "manage other users", not self-service — the
            // caller's own row is never listed here. Editing yourself
            // goes through /api/v1/me (ProfileController) instead.
            ->where('id', '!=', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action)
    {
        $user = $action->execute($request->validated(), $request->file('photo'));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(int $user)
    {
        $user = User::findOrFail($user);

        Gate::authorize('view', $user);

        return UserResource::make($user);
    }

    public function update(UpdateUserRequest $request, int $user, UpdateUserAction $action)
    {
        $user = User::findOrFail($user);

        $user = $action->execute($user, $request->validated(), $request->file('photo'));

        return UserResource::make($user);
    }

    public function status(UpdateUserStatusRequest $request, int $user, UpdateUserStatusAction $action)
    {
        $user = User::findOrFail($user);

        try {
            $user = $action->execute($user, $request->validated('status'));
        } catch (CannotDeactivateSystemAdminException $e) {
            abort(422, $e->getMessage());
        }

        return UserResource::make($user);
    }

    public function export(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('tenant')
            ->where('id', '!=', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            $pdf = app('dompdf.wrapper')->loadView('exports.users-pdf', ['users' => $users]);

            return $pdf->download('users.pdf');
        }

        return Excel::download(new UsersExport($users), 'users.xlsx');
    }
}
