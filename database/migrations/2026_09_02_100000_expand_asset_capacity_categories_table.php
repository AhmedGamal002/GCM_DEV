<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets & Supply Hub module (FRD §1.7.2) — brings the capacity-category
 * table up to the full spec. The original 2026_08_30_100100 migration
 * built only what the vehicle form's "embedded container capacity"
 * dropdown needed; this adds:
 *
 *  - `applies_to`: which asset kinds a capacity fits — container / tank /
 *    both. The asset create form only lists capacities matching the
 *    chosen asset type.
 *  - `additional_data`: the optional free-text notes field.
 *  - `updated_by`: FRD edit pages show "last updated by X — <datetime>".
 *    Lightweight audit for this module only (Vehicles/Drivers/Users don't
 *    have it yet — see ARCHITECTURE.md; a full activitylog pass is later).
 *
 * Capacity (CBM + TON) and `applies_to` are permanently locked after
 * creation — the edit form only lets the name (and notes) change,
 * because the values ripple into assets, sub-services and POs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_capacity_categories', function (Blueprint $table) {
            $table->enum('applies_to', ['container', 'tank', 'both'])->default('both')->after('name');
            $table->longText('additional_data')->nullable()->after('capacity_ton');
            $table->foreignId('updated_by')->nullable()->after('additional_data')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_capacity_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['applies_to', 'additional_data']);
        });
    }
};
