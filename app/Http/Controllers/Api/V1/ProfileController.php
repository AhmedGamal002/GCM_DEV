<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\Actions\StoreUserImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service only — separate from MeController (pure "who am I" read)
 * and from UserController (admins managing OTHER users). From one's own
 * profile only the photo and the password can change (name/email/
 * everything else is admin-managed) — and, for a client account, its
 * signature and stamp images; the password part is gated by
 * User::canChangeOwnPassword(), which every role passes. Email is also
 * immutable for a technical reason: LoginController looks users up by
 * email globally (before the tenant is known).
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        if ($request->hasFile('photo')) {
            $user->photo = $request->file('photo')->store('avatars', 'public');
        }

        StoreUserImages::into($user, [
            'signature' => $request->file('signature'),
            'stamp' => $request->file('stamp'),
        ]);

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
