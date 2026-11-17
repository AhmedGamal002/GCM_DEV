<?php

namespace App\Domain\Services\Actions;

use App\Domain\Services\Exceptions\MainServiceDeactivatedException;
use App\Domain\Services\Exceptions\SubServiceInUseException;
use App\Models\SubService;
use App\Models\User;

/**
 * FRD V01.14 §1.9.2: deactivating a sub-service is refused while it is
 * locked (ongoing trip / main service in a PO). Reactivating one needs its
 * main service to be active — a deactivated main service deactivates all
 * of its sub-services.
 */
class UpdateSubServiceStatusAction
{
    /**
     * @throws SubServiceInUseException
     * @throws MainServiceDeactivatedException
     */
    public function execute(SubService $sub, string $status, User $actor): SubService
    {
        if ($status === 'deactivated' && $sub->isLocked()) {
            throw new SubServiceInUseException;
        }

        if ($status === 'active' && ! $sub->mainService->isActive()) {
            throw new MainServiceDeactivatedException;
        }

        // operational_status is outside $fillable — set explicitly. A status chosen for the sub-service
        // itself is its own from now on, so reactivating the main service no longer brings it back.
        $sub->operational_status = $status;
        $sub->deactivated_with_main = false;
        $sub->updated_by = $actor->id;
        $sub->save();

        return $sub;
    }
}
