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
 * FRD V01.14 says deactivating / servicing an asset makes it unavailable
 * for trips ("can't be part of a trip"). It attaches no other condition,
 * so an asset sitting in a project can still be sent to maintenance or
 * deactivated — it keeps its project until the FRD defines taking it out
 * (trips, a later phase); the list then shows it as in maintenance /
 * deactivated rather than "in a project".
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
