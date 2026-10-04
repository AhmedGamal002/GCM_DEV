<?php

namespace App\Domain\Services\Actions;

use App\Domain\Services\Exceptions\MainServiceInUseException;
use App\Models\MainService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.9.1 edit page: the name and the optional data. The
 * supported vehicle categories are editable too while the service is unused
 * (client decision beyond the FRD text, same rule as a facility's service);
 * the type of service never is — its sub-services are built on it. Nothing
 * is editable once a PO includes the service. Status has its own action.
 */
class UpdateMainServiceAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     *
     * @throws MainServiceInUseException
     */
    public function execute(MainService $service, array $data, User $actor): MainService
    {
        if ($service->isInUse()) {
            throw MainServiceInUseException::inPurchaseOrder();
        }

        return DB::transaction(function () use ($service, $data, $actor) {
            $service->name = $data['name'];
            if (array_key_exists('additional_data', $data)) {
                $service->additional_data = $data['additional_data'];
            }
            $service->updated_by = $actor->id;
            $service->save();

            if (array_key_exists('vehicle_category_ids', $data)) {
                $service->vehicleCategories()->sync($data['vehicle_category_ids']);
            }

            return $service->load(['vehicleCategories', 'updatedBy']);
        });
    }
}
