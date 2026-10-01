<?php

namespace App\Domain\Services\Actions;

use App\Domain\Services\Exceptions\SubServiceInUseException;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.9.2 edit page: the name and the optional data. The asset
 * type, capacities, facilities, recycle-receipt flag and skip colour are
 * editable too while the sub-service is unlocked (client decision beyond
 * the FRD text, same rule as a facility's service); the main service never
 * is. Nothing is editable while it is locked (ongoing trip / main service in
 * a PO). Status has its own action.
 */
class UpdateSubServiceAction
{
    private const FIELDS = ['asset_type', 'requires_recycle_receipt', 'skip_color_code', 'additional_data'];

    /**
     * @param  array<string, mixed>  $data  validated input
     *
     * @throws SubServiceInUseException
     */
    public function execute(SubService $sub, array $data, User $actor): SubService
    {
        if ($sub->isLocked()) {
            throw new SubServiceInUseException;
        }

        return DB::transaction(function () use ($sub, $data, $actor) {
            $sub->name = $data['name'];
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $sub->{$field} = $data[$field];
                }
            }
            $sub->updated_by = $actor->id;
            $sub->save();

            if (array_key_exists('capacity_ids', $data)) {
                $sub->capacities()->sync($data['capacity_ids']);
            }
            if (array_key_exists('facility_ids', $data)) {
                $sub->facilities()->sync($data['facility_ids'] ?? []);
            }

            return $sub->load(['mainService', 'capacities', 'facilities', 'updatedBy']);
        });
    }
}
