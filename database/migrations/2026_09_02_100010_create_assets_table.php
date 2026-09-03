<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets & Supply Hub — FRD §1.7.3. An asset is a container (solid waste)
 * or a tank (liquid waste); it's one of the components of a trip.
 *
 * Like `vehicles`, an asset's natural identity is its name — the FRD's
 * list table has no separate "identifier code" column — so there's no
 * generated code column here.
 *
 * `contractor_id` has no FK constraint yet — the `contractors` table
 * doesn't exist until Week 5 (same mandatory pattern as
 * vehicles.contractor_id / drivers.contractor_id). The constraint is
 * added in a later migration.
 *
 * `operational_status` is the same 3 lifecycle states as a vehicle
 * (`active` / `on_maintenance` / `deactivated`). The FRD's fourth
 * "availability" value — "in a project" — is a derived state that needs
 * the Project module (Week 4); until then the list shows it as never
 * set, exactly like a vehicle's deferred "on a trip".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->enum('asset_type', ['container', 'tank']);

            $table->foreignId('asset_capacity_category_id')->constrained()->restrictOnDelete();

            $table->enum('operational_status', ['active', 'on_maintenance', 'deactivated'])->default('active');

            $table->enum('affiliation', ['gcm', 'contractor'])->default('gcm');
            $table->unsignedBigInteger('contractor_id')->nullable();

            $table->date('purchase_date')->nullable();
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('asset_capacity_category_id');
            $table->index('operational_status');
            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
