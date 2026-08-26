<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service only — separate from MeController (pure "who am I" read)
 * and from UserController (system_admin managing OTHER users). Email is
 * deliberately not editable here: LoginController looks users up by
 * email globally (before the tenant is known), so email changes have
 * session/token implications out of this week's scope — see the plan.
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->update(['name' => $request->validated('name')]);

        return UserResource::make($user);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('new_password')),
        ]);

        return response()->noContent();
    }
}
