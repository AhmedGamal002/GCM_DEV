<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a client company's account (Project Manager / Project Auditor) —
 * FRD V01.14 §1.4. Never used for GCM staff (CreateUserAction) or drivers
 * (CreateDriverAction).
 */
class CreateClientUserAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  array{photo?: ?UploadedFile, signature?: ?UploadedFile, stamp?: ?UploadedFile}  $files
     */
    public function execute(array $data, array $files, User $actor): User
    {
        return DB::transaction(function () use ($data, $files, $actor) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'additional_data' => $data['additional_data'] ?? null,
                'photo' => ! empty($files['photo']) ? $files['photo']->store('avatars', 'public') : null,
                'affiliation' => 'client',
            ]);
            $user->updated_by = $actor->id;
            $user->save();

            // The signature/stamp paths are built from the user's id, which
            // only exists after the first save.
            StoreUserImages::into($user, $files);
            $user->save();

            $user->syncRoles([$data['role']]);

            SyncClientAccess::apply($user, (int) $data['company_id'], $data['projects_scope'], $data['project_ids'] ?? []);

            return $user->load(['company', 'projects', 'roles', 'updatedBy']);
        });
    }
}
