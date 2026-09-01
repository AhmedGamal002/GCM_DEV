<?php

namespace App\Domain\Users\Actions;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CreateUserAction
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string, status: string, roles: array<int, string>, additional_data?: ?string}  $data
     */
    public function execute(array $data, ?UploadedFile $photo = null): User
    {
        return DB::transaction(function () use ($data, $photo) {
            // tenant_id is stamped automatically by BelongsToTenant's
            // creating hook — never set explicitly here.
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'additional_data' => $data['additional_data'] ?? null,
                'photo' => $photo ? $photo->store('avatars', 'public') : null,
                // Drivers can belong to a Contractor per the FRD, but
                // Contractor doesn't exist until Week 5 — always 'gcm' for now.
                'affiliation' => 'gcm',
            ]);

            $user->syncRoles($data['roles']);

            // A driver-role user always gets a matching (initially empty)
            // Driver row — every column but user_id is nullable, matching
            // the FRD: residence/license/insurance aren't required on this
            // form, only on the dedicated Drivers page (filled in later
            // via /api/v1/drivers/{id}). Without this row the user would
            // be invisible on the Drivers page — see StoreUserRequest's
            // docblock for the real bug this prevents.
            if (in_array('driver', $data['roles'], true)) {
                Driver::create(['user_id' => $user->id]);
            }

            return $user;
        });
    }
}
