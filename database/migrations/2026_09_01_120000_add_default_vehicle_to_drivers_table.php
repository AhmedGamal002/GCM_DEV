<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD's driver form, "المركبة الافتراضية – Default Car" section —
 * deferred when `drivers` was first created (Fleet/`vehicles` didn't
 * exist yet, see that migration's own docblock), now that it does.
 *
 * Nullable at the DB level (same pattern as every other driver field —
 * residence/license/insurance are all nullable here too) even though
 * the FRD marks it "لازم"/required on the form — StoreDriverRequest is
 * where that requirement is actually enforced. `nullOnDelete()` rather
 * than restrict: a vehicle can only ever be deactivated, never hard
 * deleted (see ARCHITECTURE.md's "no hard delete" rule), so this FK
 * practically never fires — but if it ever did, a driver record should
 * never block deleting a vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('default_vehicle_id')->nullable()->after('contractor_id')
                ->constrained('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_vehicle_id');
        });
    }
};
