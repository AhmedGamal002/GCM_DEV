<?php

namespace App\Domain\Services\Actions;

use App\Domain\Services\Exceptions\SubServiceInUseException;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.9.2 edit page: the name and the optional data (the skip
 * colour code and the additional data) — nothing else. The main service,
 * asset type, capacities, facilities and the recycle-receipt flag are fixed
 * at creation. Nothing is editable while it is locked (ongoing trip / main
 * service in a PO). Status has its own action.
 */
class UpdateSubServiceAction
{
    private const FIELDS = ['skip_color_code', 'additional_data'];

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

            return $sub->load(['mainService', 'capacities', 'facilities', 'updatedBy']);
        });
    }
}
