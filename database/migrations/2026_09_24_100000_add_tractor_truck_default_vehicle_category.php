<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sixth default vehicle category — "Tractor truck" / "شاحنة جرّارة (رأس
 * مقطورة)" (client request, on top of the FRD's five). New tenants get it
 * from VehicleCategory::DEFAULTS via Tenant::created; this backfills the
 * tenants that already exist.
 *
 * Explicit inserts (not the model) so the migration keeps working if the
 * model's DEFAULTS change later. Idempotent, and it skips a tenant that
 * already has a category with that slug or either name (names are unique
 * per tenant) — e.g. one the admin created by hand.
 */
return new class extends Migration
{
    private const SLUG = 'tractor_truck';

    private const NAME_EN = 'Tractor truck';

    private const NAME_AR = 'شاحنة جرّارة (رأس مقطورة)';

    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $taken = DB::table('vehicle_categories')
                ->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('slug', self::SLUG)
                    ->orWhere('name_en', self::NAME_EN)
                    ->orWhere('name_ar', self::NAME_AR))
                ->exists();

            if ($taken) {
                continue;
            }

            DB::table('vehicle_categories')->insert([
                'tenant_id' => $tenantId,
                'slug' => self::SLUG,
                'name_en' => self::NAME_EN,
                'name_ar' => self::NAME_AR,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Removes the default only where nothing uses it — the pivots cascade,
     * so deleting a category that's in use would silently strip driver
     * qualifications and asset compatibility.
     */
    public function down(): void
    {
        $ids = DB::table('vehicle_categories')->where('slug', self::SLUG)->pluck('id');

        foreach ($ids as $id) {
            $inUse = DB::table('vehicles')->where('vehicle_category_id', $id)->exists()
                || DB::table('driver_vehicle_categories')->where('vehicle_category_id', $id)->exists()
                || DB::table('asset_vehicle_categories')->where('vehicle_category_id', $id)->exists();

            if (! $inUse) {
                DB::table('vehicle_categories')->where('id', $id)->delete();
            }
        }
    }
};
