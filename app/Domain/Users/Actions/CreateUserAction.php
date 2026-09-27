<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Never used to create a driver — see StoreUserRequest's docblock. Driver
 * accounts go exclusively through App\Domain\Drivers\Actions\CreateDriverAction.
 */
class CreateUserAction
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string, status: string, roles: array<int, string>, additional_data?: ?string}  $data
     */
    public function execute(array $data, ?UploadedFile $photo = null): User
    {
        // tenant_id is stamped automatically by BelongsToTenant's creating
        // hook — never set explicitly here.
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
            'additional_data' => $data['additional_data'] ?? null,
            'photo' => $photo ? $photo->store('avatars', 'public') : null,
            'affiliation' => 'gcm',
        ]);
        // FRD "اخر تحديث: تم بواسطة X" — audit field outside $fillable, same as assets.
        $user->updated_by = auth()->id();
        $user->save();

        $user->syncRoles($data['roles']);

        return $user;
    }
}
