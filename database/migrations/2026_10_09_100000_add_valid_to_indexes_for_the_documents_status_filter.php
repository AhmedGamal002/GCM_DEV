<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lists' "Documents Status" filter asks, per vehicle / driver, "is there a document with a date before today / up
 * to today + N days / after that?" — a handful of correlated EXISTS probes over the documents (or entry permits) of
 * that one row. With only (vehicle_id, type) to go on, MySQL on a 50,000-vehicle / 250,000-document table sometimes
 * picked a plan that scanned the whole tenant's documents for every vehicle (a count that never finished, 46+ minutes).
 * A (parent, valid_to) index lets each probe be answered from the index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->index(['vehicle_id', 'valid_to'], 'vehicle_documents_vehicle_valid_to_index');
        });

        Schema::table('driver_entry_permits', function (Blueprint $table) {
            $table->index(['driver_id', 'valid_to'], 'driver_entry_permits_driver_valid_to_index');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropIndex('vehicle_documents_vehicle_valid_to_index');
        });

        Schema::table('driver_entry_permits', function (Blueprint $table) {
            $table->dropIndex('driver_entry_permits_driver_valid_to_index');
        });
    }
};
