<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 7.1: the quota ledger's `trip_id` (created in part 6.1 without a
 * foreign key, since trips didn't exist yet) now points at trips. Trips are
 * never deleted, so the ledger rows that reference one can't be orphaned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_ledger_entries', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quota_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
    }
};
