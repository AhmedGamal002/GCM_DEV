<?php

namespace App\Http\Requests\Services\Concerns;

use App\Models\AssetCapacityCategory;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use Illuminate\Validation\Validator;

/**
 * Cross-field checks shared by the sub-service create / edit requests —
 * the parts `rules()` can't express on its own:
 *  - every chosen capacity must fit the chosen asset type (container / tank
 *    / both — FRD "based on the previous choice, the matching capacities
 *    appear");
 *  - intermediate facilities are required unless the main service is a
 *    supplying one (FRD §1.9.2 note);
 *  - a facility newly linked must be active (a deactivated facility "can't
 *    be chosen for new sub-services or trips"); one already linked may stay.
 *
 * IDs are compared as ints — a real HTML form sends them as strings.
 */
trait ValidatesSubServiceComponents
{
    /**
     * @param  array<int, int|string>  $capacityIds
     */
    protected function checkCapacitiesFitType(Validator $validator, string $assetType, array $capacityIds): void
    {
        $misfits = AssetCapacityCategory::whereIn('id', array_map('intval', $capacityIds))
            ->get()
            ->reject(fn (AssetCapacityCategory $c) => $c->fitsType($assetType));

        if ($misfits->isNotEmpty()) {
            $validator->errors()->add('capacity_ids', __('Choose only capacities that fit the selected asset type.'));
        }
    }

    /**
     * @param  array<int, int|string>|null  $facilityIds
     * @param  array<int, int>  $alreadyLinked
     */
    protected function checkFacilities(Validator $validator, MainService $main, ?array $facilityIds, array $alreadyLinked = []): void
    {
        $ids = array_map('intval', $facilityIds ?? []);

        if ($ids === [] && ! $main->isSupplying()) {
            $validator->errors()->add('facility_ids', __('Choose at least one intermediate facility (optional only for supplying services).'));

            return;
        }

        $newIds = array_diff($ids, $alreadyLinked);
        if ($newIds !== [] && IntermediateFacility::whereIn('id', $newIds)->where('operational_status', '!=', 'active')->exists()) {
            $validator->errors()->add('facility_ids', __('A deactivated facility can\'t be chosen.'));
        }
    }
}
