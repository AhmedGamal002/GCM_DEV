<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Fleet — FRD §1.5. A vehicle's natural identity is its plate
 * (the FRD asks for no separate "identifier code" the way it does for
 * users), so uniqueness is enforced on (tenant_id, plate) instead of a
 * generated code column.
 *
 * `contractor_id` has no FK constraint yet — the `contractors` table
 * doesn't exist until Week 5 (same deliberate pattern as
 * users.contractor_id). The constraint is added in a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            // "Plate registry (English)" — split into a letters part and a
            // numbers part per the FRD form.
            $table->string('plate_letters');
            $table->string('plate_numbers');

            $table->foreignId('vehicle_category_id')->constrained()->restrictOnDelete();

            // "هل تحتوي المركبة علي حاوية مدمجة" — if yes, its type and a
            // capacity picked from the tenant's asset capacity categories.
            $table->boolean('has_embedded_container')->default(false);
            $table->enum('embedded_container_type', ['container', 'tank'])->nullable();
            $table->unsignedBigInteger('embedded_asset_capacity_category_id')->nullable();

            // 3 lifecycle states (not 2) per ARCHITECTURE.md — both
            // on_maintenance and deactivated take the vehicle out of trip
            // selection.
            $table->enum('operational_status', ['active', 'on_maintenance', 'deactivated'])->default('active');

            $table->enum('affiliation', ['gcm', 'contractor'])->default('gcm');
            $table->unsignedBigInteger('contractor_id')->nullable();

            $table->longText('additional_data')->nullable();
            $table->string('photo_front')->nullable();
            $table->string('photo_back')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('vehicle_category_id');
            $table->index('operational_status');
            $table->unique(['tenant_id', 'plate_letters', 'plate_numbers']);

            $table->foreign('embedded_asset_capacity_category_id')
                ->references('id')->on('asset_capacity_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
