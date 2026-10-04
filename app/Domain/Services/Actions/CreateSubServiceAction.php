<?php

namespace App\Domain\Services\Actions;

use App\Models\SubService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSubServiceAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(array $data, User $actor): SubService
    {
        return DB::transaction(function () use ($data, $actor) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $sub = new SubService([
                'name' => $data['name'],
                'asset_type' => $data['asset_type'],
                'requires_recycle_receipt' => (bool) ($data['requires_recycle_receipt'] ?? false),
                'skip_color_code' => $data['skip_color_code'] ?? null,
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // Outside $fillable — set explicitly.
            $sub->main_service_id = $data['main_service_id'];
            $sub->operational_status = $data['operational_status'] ?? 'active';
            $sub->updated_by = $actor->id;
            $sub->save();

            $sub->capacities()->sync($data['capacity_ids']);
            $sub->facilities()->sync($data['facility_ids'] ?? []);

            return $sub->load(['mainService', 'capacities', 'facilities', 'updatedBy']);
        });
    }
}
