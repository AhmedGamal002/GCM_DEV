<?php

namespace App\Domain\Services\Actions;

use App\Domain\Services\Exceptions\MainServiceInUseException;
use App\Models\MainService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.9.1: deactivating a main service deactivates every
 * sub-service under it, and is refused while the service (or any of its
 * sub-services) is in use. Reactivating it brings back exactly the
 * sub-services that its deactivation turned off (client request — the FRD
 * only defines the cascade one way): they carry `deactivated_with_main`.
 * One that was already off on its own before stays off.
 */
class UpdateMainServiceStatusAction
{
    /**
     * @throws MainServiceInUseException
     */
    public function execute(MainService $service, string $status, User $actor): MainService
    {
        if ($status === 'deactivated') {
            if ($service->isInUse()) {
                throw MainServiceInUseException::inPurchaseOrder();
            }

            foreach ($service->subServices as $sub) {
                if ($sub->hasOngoingTrips()) {
                    throw MainServiceInUseException::lockedSubService();
                }
            }
        }

        return DB::transaction(function () use ($service, $status, $actor) {
            // operational_status is outside $fillable — set explicitly.
            $service->operational_status = $status;
            $service->updated_by = $actor->id;
            $service->save();

            if ($status === 'deactivated') {
                $service->subServices()
                    ->where('operational_status', 'active')
                    ->update(['operational_status' => 'deactivated', 'deactivated_with_main' => true, 'updated_by' => $actor->id, 'updated_at' => now()]);
            } else {
                $service->subServices()
                    ->where('operational_status', 'deactivated')
                    ->where('deactivated_with_main', true)
                    ->update(['operational_status' => 'active', 'deactivated_with_main' => false, 'updated_by' => $actor->id, 'updated_at' => now()]);
            }

            return $service;
        });
    }
}
