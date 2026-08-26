<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUserAction
{
    /**
     * Status is deliberately not handled here — it goes exclusively
     * through UpdateUserStatusAction, which guards against deactivating
     * a system_admin. Email is immutable (see StoreUserRequest/FRD notes).
     *
     * @param  array{name: string, phone: string, additional_data?: ?string, roles: array<int, string>}  $data
     */
    public function execute(User $user, array $data, ?UploadedFile $photo = null): User
    {
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->additional_data = $data['additional_data'] ?? null;

        if ($photo) {
            $user->photo = $photo->store('avatars', 'public');
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return $user;
    }
}
