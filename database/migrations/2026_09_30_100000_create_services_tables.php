<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services — FRD V01.14 §1.9.
 *
 * A main service is a category (non-hazardous / hazardous waste, or
 * supplying) that groups sub-services and names the vehicle categories that
 * can perform it; it is the building block of a PO. A sub-service is what a
 * trip actually delivers: one asset type (container / tank), the asset
 * capacities it fits, the intermediate facilities its waste may go to, an
 * optional skip colour code and whether a trip needs a recycle receipt.
 *
 * All pivots cascade on delete of the service side; the other side
 * (vehicle category / capacity / facility) is guarded in code — a vehicle
 * category can't be deleted while a main service lists it, and capacities /
 * facilities are never deleted at all (only deactivated).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('main_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->enum('service_type', ['non_hazardous', 'hazardous', 'supplying']);
            $table->enum('operational_status', ['active', 'deactivated'])->default('active');
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index('service_type');
            $table->index('operational_status');
        });

        Schema::create('main_service_vehicle_category', function (Blueprint $table) {
            $table->foreignId('main_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['main_service_id', 'vehicle_category_id']);
        });

        Schema::create('sub_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('main_service_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->enum('asset_type', ['container', 'tank']);
            $table->boolean('requires_recycle_receipt')->default(false);
            $table->string('skip_color_code', 32)->nullable();
            $table->enum('operational_status', ['active', 'deactivated'])->default('active');
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Two main services may each have e.g. a "General waste" sub-service.
            $table->unique(['main_service_id', 'name']);
            $table->index('tenant_id');
            $table->index('operational_status');
        });

        Schema::create('sub_service_asset_capacity', function (Blueprint $table) {
            $table->foreignId('sub_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_capacity_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['sub_service_id', 'asset_capacity_category_id'], 'sub_service_capacity_primary');
        });

        Schema::create('sub_service_facility', function (Blueprint $table) {
            $table->foreignId('sub_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intermediate_facility_id')->constrained()->cascadeOnDelete();
            $table->primary(['sub_service_id', 'intermediate_facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_service_facility');
        Schema::dropIfExists('sub_service_asset_capacity');
        Schema::dropIfExists('sub_services');
        Schema::dropIfExists('main_service_vehicle_category');
        Schema::dropIfExists('main_services');
    }
};
