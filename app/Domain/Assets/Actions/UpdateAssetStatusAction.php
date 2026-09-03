<?php

namespace App\Domain\Assets\Actions;

use App\Domain\Assets\Exceptions\CannotDeactivateAssetException;
use App\Models\Asset;
use App\Models\User;

/**
 * Mirrors UpdateVehicleStatusAction. The FRD splits authority:
 * on_maintenance is (System Admin / Data Entry); Deactivated and
 * reactivation-from-deactivated are System Admin only. The Policy gates
 * the request; this second check keeps the rule true even if the action
 * is called directly.
 *
 * FRD also says deactivating / servicing an asset makes it unavailable
 * for trips and "cannot be done while it's out with a vehicle/project" —
 * there's nothing to check that against until the Trip/Project modules
 * exist (Week 4/7), so that guard is deferred.
 */
class UpdateAssetStatusAction
{
    public function execute(Asset $asset, string $status, User $actor): Asset
    {
        $touchesDeactivation = $status === 'deactivated' || $asset->operational_status === 'deactivated';

        if ($touchesDeactivation && ! $actor->hasRole('system_admin')) {
            throw new CannotDeactivateAssetException;
        }

        // operational_status is outside $fillable — set explicitly.
        $asset->operational_status = $status;
        $asset->updated_by = $actor->id;
        $asset->save();

        return $asset;
    }
}
