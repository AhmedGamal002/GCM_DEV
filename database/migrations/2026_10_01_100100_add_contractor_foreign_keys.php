<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the foreign key on the `contractor_id` columns that users, drivers,
 * vehicles and assets have carried since Phase 1 (they were plain nullable
 * integers while the `contractors` table didn't exist). `restrictOnDelete`:
 * a contractor that still owns resources can never be removed from under
 * them — and contractors are only ever deactivated anyway.
 */
return new class extends Migration
{
    private const TABLES = ['users', 'drivers', 'vehicles', 'assets'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreign('contractor_id')->references('id')->on('contractors')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['contractor_id']);
            });
        }
    }
};
