<?php

namespace App\Domain\Assets\Actions;

use App\Models\Asset;
use App\Models\User;

/**
 * FRD V01.14 role page for Data Entry ("إدارة الموارد: كل الصلاحيات
 * (انشاء / تعديل / تعطيل)") widens this beyond V01.09, which reserved
 * deactivating an asset for System Admin only — data_entry may now
 * deactivate/reactivate too, same as on_maintenance. (V01.14 §1.7.1's
 * own policy paragraph still says "مدير النظام فقط" for this — an
 * internal contradiction in that FRD version; confirmed with the client
 * as their mistake, intent is admin-parity for data_entry here.)
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
        // operational_status is outside $fillable — set explicitly.
        $asset->operational_status = $status;
        $asset->updated_by = $actor->id;
        $asset->save();

        return $asset;
    }
}
