<?php

namespace App\Domain\Services\Actions;

use App\Models\MainService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateMainServiceAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(array $data, User $actor): MainService
    {
        return DB::transaction(function () use ($data, $actor) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $service = new MainService([
                'name' => $data['name'],
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // Outside $fillable — set explicitly.
            $service->service_type = $data['service_type'];
            $service->operational_status = $data['operational_status'] ?? 'active';
            $service->updated_by = $actor->id;
            $service->save();

            $service->vehicleCategories()->sync($data['vehicle_category_ids']);

            return $service->load(['vehicleCategories', 'updatedBy']);
        });
    }
}
