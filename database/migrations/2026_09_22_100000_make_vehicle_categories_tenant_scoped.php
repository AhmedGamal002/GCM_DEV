<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle categories stop being a fixed global list (FRD §1.5.2, five
 * entries shared by every tenant) and become per-tenant data a
 * system_admin can manage (client request, outside the FRD). A global
 * table can't be editable — one tenant's admin would rename or delete
 * categories for every other tenant.
 *
 * Existing data is preserved: each tenant gets its own copy of every
 * global category, and that tenant's vehicles, drivers' qualified
 * categories and assets' compatible categories are re-pointed at the
 * copies before the global rows are dropped. (New tenants get the five
 * defaults from VehicleCategory::seedDefaultsFor(), via Tenant::created.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $globals = DB::table('vehicle_categories')->whereNull('tenant_id')->get();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($globals as $global) {
                $newId = DB::table('vehicle_categories')->insertGetId([
                    'tenant_id' => $tenantId,
                    'slug' => $global->slug,
                    'name_en' => $global->name_en,
                    'name_ar' => $global->name_ar,
                    'created_at' => $global->created_at,
                    'updated_at' => $global->updated_at,
                ]);

                DB::table('vehicles')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_category_id', $global->id)
                    ->update(['vehicle_category_id' => $newId]);

                DB::table('driver_vehicle_categories')
                    ->where('vehicle_category_id', $global->id)
                    ->whereIn('driver_id', DB::table('drivers')->where('tenant_id', $tenantId)->select('id'))
                    ->update(['vehicle_category_id' => $newId]);

                DB::table('asset_vehicle_categories')
                    ->where('vehicle_category_id', $global->id)
                    ->whereIn('asset_id', DB::table('assets')->where('tenant_id', $tenantId)->select('id'))
                    ->update(['vehicle_category_id' => $newId]);
            }
        }

        DB::table('vehicle_categories')->whereNull('tenant_id')->delete();

        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'name_en']);
            $table->unique(['tenant_id', 'name_ar']);
        });
    }

    /**
     * Collapses back to one global row per slug (the lowest id) and
     * re-points every reference at it. Any category a tenant added
     * itself (slug not in the surviving set) is folded into the slug it
     * shares, or kept as its own global row.
     */
    public function down(): void
    {
        // MySQL refuses to drop the unique indexes while the tenant_id
        // foreign key still leans on them — drop the constraint first.
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropUnique(['tenant_id', 'name_en']);
            $table->dropUnique(['tenant_id', 'name_ar']);
        });

        $keepers = DB::table('vehicle_categories')
            ->selectRaw('min(id) as id, slug')
            ->groupBy('slug')
            ->pluck('id', 'slug');

        foreach (DB::table('vehicle_categories')->get() as $row) {
            $keeperId = (int) $keepers[$row->slug];
            if ($keeperId === (int) $row->id) {
                continue;
            }

            DB::table('vehicles')->where('vehicle_category_id', $row->id)->update(['vehicle_category_id' => $keeperId]);

            foreach (['driver_vehicle_categories' => 'driver_id', 'asset_vehicle_categories' => 'asset_id'] as $pivot => $owner) {
                // Drop a duplicate (owner, keeper) pair before re-pointing,
                // so the pivot's own unique key can't be violated.
                // (ids fetched first — MySQL can't delete from a table
                // while sub-selecting from it, error 1093)
                $alreadyOnKeeper = DB::table($pivot)->where('vehicle_category_id', $keeperId)->pluck($owner)->all();
                DB::table($pivot)
                    ->where('vehicle_category_id', $row->id)
                    ->whereIn($owner, $alreadyOnKeeper)
                    ->delete();
                DB::table($pivot)->where('vehicle_category_id', $row->id)->update(['vehicle_category_id' => $keeperId]);
            }

            DB::table('vehicle_categories')->where('id', $row->id)->delete();
        }

        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
            $table->unique('slug');
        });
    }
};
