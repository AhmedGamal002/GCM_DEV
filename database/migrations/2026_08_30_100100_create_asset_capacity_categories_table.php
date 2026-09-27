<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal version, built now only so the vehicle form's "embedded
 * container capacity" dropdown (FRD §1.5.3) has real data to select.
 * Per the FRD, capacity carries CBM *and* TON together — not a choice
 * of one unit.
 *
 * The Assets module (FRD §1.7.2) extends this table in
 * 2026_09_02_100000_expand_asset_capacity_categories_table.php:
 * `applies_to` (container/tank/both), `additional_data`, `updated_by`.
 * The "name is the only editable field after creation" policy lives in
 * UpdateAssetCapacityCategoryRequest / UpdateAssetCapacityCategoryAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_capacity_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('capacity_cbm', 8, 2);
            $table->decimal('capacity_ton', 8, 2);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_capacity_categories');
    }
};
