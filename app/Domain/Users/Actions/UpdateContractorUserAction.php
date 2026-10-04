<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * FRD V01.14 §1.4: every field of a contractor user is editable by (System
 * Admin / Data Entry), email included. Status goes through
 * UpdateUserStatusAction, like every other account. The role never changes.
 */
class UpdateContractorUserAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(User $user, array $data, ?UploadedFile $photo, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $photo, $actor) {
            $user->name = $data['name'];
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            $user->phone = $data['phone'];
            $user->additional_data = $data['additional_data'] ?? null;

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if ($photo) {
                $user->photo = $photo->store('avatars', 'public');
            }

            $user->updated_by = $actor->id;
            $user->save();

            SyncContractorAccess::apply($user, (int) $data['contractor_id']);

            return $user->load(['contractor', 'roles', 'updatedBy']);
        });
    }
}
