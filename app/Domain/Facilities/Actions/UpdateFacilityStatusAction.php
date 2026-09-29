<?php

namespace App\Domain\Facilities\Actions;

use App\Models\IntermediateFacility;
use App\Models\User;

/**
 * FRD V01.14 §1.8: deactivating / reactivating a facility is (System Admin /
 * Data Entry), from the details page. The FRD names no guard for it; once
 * sub-services and trips exist this is where any "still in use" rule would go.
 */
class UpdateFacilityStatusAction
{
    public function execute(IntermediateFacility $facility, string $status, User $actor): IntermediateFacility
    {
        // operational_status is outside $fillable — set explicitly.
        $facility->operational_status = $status;
        $facility->updated_by = $actor->id;
        $facility->save();

        return $facility;
    }
}
