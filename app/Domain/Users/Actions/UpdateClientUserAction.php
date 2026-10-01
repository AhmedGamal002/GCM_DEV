<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * FRD V01.14 §1.4: every field of a client account is editable by (System
 * Admin / Data Entry). Email stays immutable and status goes through
 * UpdateUserStatusAction, like every other account.
 */
class UpdateClientUserAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  array{photo?: ?UploadedFile, signature?: ?UploadedFile, stamp?: ?UploadedFile}  $files
     */
    public function execute(User $user, array $data, array $files, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $files, $actor) {
            $user->name = $data['name'];
            $user->phone = $data['phone'];
            $user->additional_data = $data['additional_data'] ?? null;

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if (! empty($files['photo'])) {
                $user->photo = $files['photo']->store('avatars', 'public');
            }

            StoreUserImages::into($user, $files);

            $user->updated_by = $actor->id;
            $user->save();

            $user->syncRoles([$data['role']]);

            SyncClientAccess::apply($user, (int) $data['company_id'], $data['projects_scope'], $data['project_ids'] ?? []);

            return $user->load(['company', 'projects', 'roles', 'updatedBy']);
        });
    }
}
