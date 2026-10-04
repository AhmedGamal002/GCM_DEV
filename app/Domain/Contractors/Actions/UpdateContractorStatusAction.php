<?php

namespace App\Domain\Contractors\Actions;

use App\Models\Contractor;
use App\Models\User;

/**
 * FRD V01.14 §1.10.4: deactivating / re-activating a contractor is
 * (System Admin / Data Entry).
 *
 * The FRD says nothing about deactivating a contractor also deactivating its
 * drivers, vehicles or containers, so nothing cascades — the status is only
 * the contractor's own. Whether a deactivated contractor's resources can
 * still be picked for a trip is for the trips module to decide.
 */
class UpdateContractorStatusAction
{
    public function execute(Contractor $contractor, string $status, User $actor): Contractor
    {
        // operational_status is outside $fillable — set explicitly.
        $contractor->operational_status = $status;
        $contractor->updated_by = $actor->id;
        $contractor->save();

        return $contractor;
    }
}
