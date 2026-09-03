<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD §1.7.3 asset create form — "تصنيف المركبات المتناسب مع هذه السعة /
 * Compatible Vehicles Category": each asset is tagged with any number of
 * the 5 fixed vehicle categories it can be carried by (checkbox list,
 * required — at least one). Set at creation, never editable afterwards
 * (the asset edit page only allows the name to change).
 *
 * Same shape as `driver_vehicle_categories`: `vehicle_categories` is a
 * global reference table (no tenant_id, like `roles`), so tenant scoping
 * comes transitively through `asset_id` → `assets.tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_vehicle_categories', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'vehicle_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_vehicle_categories');
    }
};
