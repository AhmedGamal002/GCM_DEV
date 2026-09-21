<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Concerns\BelongsToTenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Single login endpoint for both the web panel and (eventually) the
 * driver mobile app, per Sanctum's dual-mode design: a request from a
 * SANCTUM_STATEFUL_DOMAINS origin gets a session cookie (what the Blade
 * login page uses); any other request (mobile/third-party) gets a
 * bearer token instead. Both paths verify credentials identically.
 *
 * Credentials are looked up unscoped (bypassing BelongsToTenant) because
 * the tenant isn't known until AFTER we know who the user is — see
 * BelongsToTenant's docblock. Auth::guard('web')->attempt() isn't used
 * here: for non-stateful (mobile) requests there is no session bound to
 * the request at all (EnsureFrontendRequestsAreStateful only starts one
 * for frontend-origin requests), so SessionGuard::attempt() would throw.
 */
class LoginController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScope(BelongsToTenant::class)
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Checked only after the credentials themselves are confirmed
        // correct — revealing "this account is deactivated" to someone
        // who doesn't actually know the password would leak account
        // status to an unauthenticated caller (FRD: deactivated accounts
        // get their own distinct message, not the generic failure one).
        if ($user->status === 'deactivated') {
            throw ValidationException::withMessages([
                'email' => __('auth.deactivated'),
            ]);
        }

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return response()->json(['user' => $this->userPayload($user)]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'tenant' => [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
            ],
        ];
    }
}
