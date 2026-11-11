<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders (contracts) — FRD V01.14 §1.13, Phase 3 part 6.1.
 *
 *  - purchase_orders: one contract for ONE project of one client company.
 *    `number` is the company's short name + the paper contract's number
 *    (ALN-1200): digits only, up to 10, leading zeros kept, so it is a string.
 *    It is unique per tenant and, since POs are never deleted, a number is
 *    never handed out again — not even once the PO is deactivated.
 *    The currency is the Saudi riyal for every PO (no currency column, no
 *    VAT, decision أ4); amounts are decimal(12,2).
 *  - purchase_order_services: the PO's lines ("بنود") — one main service
 *    each, with its quota. The totals (max trips × trip cost, …) are computed,
 *    never stored. One main service appears at most once in a PO.
 *  - quota_ledger_entries: the append-only quota ledger. Every trip that
 *    consumes a line writes a `consume` row; cancelling it (or moving it to
 *    another line) writes a `release` row that reverses one consume
 *    (`reverses_id`, unique, so a consume can only ever be reversed once).
 *    The balance of a line is the sum of its rows. `trip_id` has no foreign
 *    key yet — the trips table arrives in part 7.1, which adds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();

            $table->string('number', 24);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('region')->nullable();

            $table->enum('operational_status', ['active', 'deactivated'])->default('active');
            $table->string('attachment_path')->nullable();
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['project_id', 'operational_status']);
            $table->index('company_id');
        });

        Schema::create('purchase_order_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('main_service_id')->constrained()->restrictOnDelete();

            $table->enum('quota_method', ['trips_only', 'trips_and_ancillary']);
            $table->unsignedInteger('max_trips');
            $table->decimal('trip_cost', 12, 2);
            $table->decimal('ancillary_cost_per_trip', 12, 2)->default(0);
            $table->unsignedTinyInteger('alert_percent');

            $table->timestamps();

            $table->unique(['purchase_order_id', 'main_service_id']);
            $table->index('main_service_id');
        });

        Schema::create('quota_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_service_id')->constrained()->restrictOnDelete();
            // The FK to trips is added by part 7.1 (2026_11_15_100100_add_trip_fk_to_quota_ledger).
            $table->unsignedBigInteger('trip_id')->nullable();

            $table->enum('type', ['consume', 'release']);
            $table->integer('trips_delta');
            $table->decimal('trip_cost_delta', 12, 2);
            $table->decimal('ancillary_cost_delta', 12, 2)->default(0);
            // A release row points at the consume row it reverses; unique, so no consume is reversed twice.
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('quota_ledger_entries')->restrictOnDelete();

            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['purchase_order_service_id', 'occurred_at']);
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_ledger_entries');
        Schema::dropIfExists('purchase_order_services');
        Schema::dropIfExists('purchase_orders');
    }
};
