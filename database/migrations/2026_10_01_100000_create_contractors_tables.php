<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contractor companies ("مقاولو الباطن") — FRD V01.14 §1.10. A contractor is
 * a company bound to GCM by a contract that supplies resources for its trips:
 * drivers, vehicles and containers / tanks. Those resources keep their
 * `affiliation` + `contractor_id` columns (created long before this table
 * existed); the next migration finally puts the foreign keys on them.
 *
 * `prefix` is the "الاسم المختصر" — 3 English letters, unique among the
 * tenant's contractors, locked after creation. `code` (the ID shown in the
 * lists) is the prefix + a "C" + a running number — ALN-C0001 — so a
 * contractor's ID can never equal a client company's (ALN-0001) even when the
 * two share the same letters. `sequence` is that number, one counter per
 * tenant, assigned in Contractor::booted().
 *
 * `contractor_main_service` holds the "Certified services" checkboxes: the
 * main services the contractor supports. No rows = supports everything (the
 * FRD: "في حال عدم التحديد يعتبر الكل"). Cascades on both sides: a service is
 * never hard-deleted, and a contractor is never hard-deleted either.
 *
 * Not here yet, on purpose: the "contractor representative account" — it
 * picks one of the contractor's user accounts (FRD §1.4), which don't exist
 * until the contractor user role is built.
 *
 * The three attachments are stored on the private `local` disk and served
 * through an authorised download route; the logo is public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('sequence');
            $table->string('code', 16);
            $table->string('name');
            $table->string('prefix', 3);
            $table->string('trading_name')->nullable();
            $table->string('logo')->nullable();

            $table->string('phone', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('location_url', 2048)->nullable();

            $table->string('contract_number', 50)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_attachment_path')->nullable();

            $table->string('cr_number', 50)->nullable();
            $table->string('cr_attachment_path')->nullable();

            $table->string('tax_number', 50)->nullable();
            $table->string('tax_attachment_path')->nullable();

            $table->enum('operational_status', ['active', 'deactivated'])->default('active');
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'sequence']);
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'prefix']);
            $table->index('operational_status');
        });

        Schema::create('contractor_main_service', function (Blueprint $table) {
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('main_service_id')->constrained()->cascadeOnDelete();
            $table->primary(['contractor_id', 'main_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_main_service');
        Schema::dropIfExists('contractors');
    }
};
