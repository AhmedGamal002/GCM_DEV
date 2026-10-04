<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a contractor company's user (FRD V01.14 §1.4) — role
 * `contractor_user`, affiliation `contractor`. Never used for GCM staff
 * (CreateUserAction), drivers (CreateDriverAction) or client accounts
 * (CreateClientUserAction).
 */
class CreateContractorUserAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(array $data, ?UploadedFile $photo, User $actor): User
    {
        return DB::transaction(function () use ($data, $photo, $actor) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'additional_data' => $data['additional_data'] ?? null,
                'photo' => $photo ? $photo->store('avatars', 'public') : null,
                'affiliation' => 'contractor',
            ]);
            $user->updated_by = $actor->id;
            $user->save();

            $user->syncRoles([User::CONTRACTOR_ROLE]);

            SyncContractorAccess::apply($user, (int) $data['contractor_id']);

            return $user->load(['contractor', 'roles', 'updatedBy']);
        });
    }
}
