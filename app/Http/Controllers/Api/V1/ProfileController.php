<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service only — separate from MeController (pure "who am I" read)
 * and from UserController (admins managing OTHER users). Per the FRD the
 * only thing changed from one's own profile is the photo (name/email/
 * everything else is admin-managed), plus the password for client/
 * contractor users only (User::canChangeOwnPassword()). Email is also
 * immutable for a technical reason: LoginController looks users up by
 * email globally (before the tenant is known).
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->photo = $request->file('photo')->store('avatars', 'public');
        $user->save();

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
