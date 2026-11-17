<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deactivating a main service deactivates its sub-services; reactivating it brings back exactly
 * those (client request) — not the ones that were already off on their own. The flag remembers
 * which sub-services the main service's deactivation turned off.
 *
 * Backfill: sub-services that are off right now under a deactivated main service and were changed
 * in the same moment as it (the cascade writes both in one transaction) are marked, so reactivating
 * a main service that is already deactivated today brings them back too. One that was edited again
 * since keeps its later timestamp and stays off — it can still be reactivated on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->boolean('deactivated_with_main')->default(false)->after('operational_status');
        });

        $mains = DB::table('main_services')
            ->where('operational_status', 'deactivated')
            ->whereNotNull('updated_at')
            ->get(['id', 'updated_at']);

        foreach ($mains as $main) {
            $at = Carbon::parse($main->updated_at);

            DB::table('sub_services')
                ->where('main_service_id', $main->id)
                ->where('operational_status', 'deactivated')
                ->whereBetween('updated_at', [$at->copy()->subSeconds(2), $at->copy()->addSeconds(2)])
                ->update(['deactivated_with_main' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->dropColumn('deactivated_with_main');
        });
    }
};
