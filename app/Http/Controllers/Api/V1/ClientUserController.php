<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\Actions\CreateClientUserAction;
use App\Domain\Users\Actions\UpdateClientUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientUsers\StoreClientUserRequest;
use App\Http\Requests\ClientUsers\UpdateClientUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Client accounts (Project Manager / Project Auditor) — FRD V01.14 §1.4.
 * Listing, reading and status changes go through the shared /users
 * endpoints; only create and edit live here, because a client account has
 * a company, project access and signature/stamp that the generic form does
 * not (and the generic form would re-role it — see UserPolicy::update()).
 *
 * No implicit route-model binding — see UserController's docblock.
 */
class ClientUserController extends Controller
{
    public function store(StoreClientUserRequest $request, CreateClientUserAction $action)
    {
        $user = $action->execute($request->validated(), $this->extractFiles($request), $request->user());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function update(UpdateClientUserRequest $request, int $user, UpdateClientUserAction $action)
    {
        $user = User::findOrFail($user);

        $user = $action->execute($user, $request->validated(), $this->extractFiles($request), $request->user());

        return UserResource::make($user);
    }

    /**
     * @return array{photo: ?UploadedFile, signature: ?UploadedFile, stamp: ?UploadedFile}
     */
    private function extractFiles(Request $request): array
    {
        return [
            'photo' => $request->file('photo'),
            'signature' => $request->file('signature'),
            'stamp' => $request->file('stamp'),
        ];
    }
}
