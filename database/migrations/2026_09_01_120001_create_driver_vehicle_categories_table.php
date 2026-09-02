<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD's driver form, "اختيار نوع المركبات المؤهل لقيادتها" — a driver
 * can be qualified to drive any number of the 5 fixed vehicle
 * categories (checkbox list, required — at least one). Many-to-many:
 * `vehicle_categories` is a global reference table (no tenant_id, same
 * as `roles`), so tenant scoping here comes transitively through
 * `driver_id` → `drivers.tenant_id`, same reasoning as
 * `model_has_roles` needing no tenant_id of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_vehicle_categories', function (Blueprint $table) {
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['driver_id', 'vehicle_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_vehicle_categories');
    }
};
