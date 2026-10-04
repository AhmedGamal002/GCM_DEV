<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\Actions\CreateContractorUserAction;
use App\Domain\Users\Actions\UpdateContractorUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContractorUsers\StoreContractorUserRequest;
use App\Http\Requests\ContractorUsers\UpdateContractorUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

/**
 * Contractor users — FRD V01.14 §1.4. Listing, reading and status changes go
 * through the shared /users endpoints; only create and edit live here,
 * because a contractor user has a contractor that the generic form does not
 * (and the generic form would re-role it — see UserPolicy::update()).
 *
 * No implicit route-model binding — see UserController's docblock.
 */
class ContractorUserController extends Controller
{
    public function store(StoreContractorUserRequest $request, CreateContractorUserAction $action)
    {
        $user = $action->execute($request->validated(), $request->file('photo'), $request->user());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function update(UpdateContractorUserRequest $request, int $user, UpdateContractorUserAction $action)
    {
        $user = User::findOrFail($user);

        $user = $action->execute($user, $request->validated(), $request->file('photo'), $request->user());

        return UserResource::make($user);
    }
}
