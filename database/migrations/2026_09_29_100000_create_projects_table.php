<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client projects — FRD V01.14 §1.12. A project is a work site belonging
 * to one client company: the place trips leave from to collect waste.
 *
 * `code` is the FRD's "الرقم التعريفي" — built from the owning company's
 * short name (prefix) so numbers stay unique per company, e.g. ALN-P0001;
 * `sequence` is the running number per company, assigned in
 * Project::booted(). Because the code is built from the company, the
 * company is locked after creation (like the prefix itself).
 *
 * Not here yet, on purpose:
 *  - the "project representative account" (needs client accounts, FRD §1.4,
 *    Part 6 of Phase 2);
 *  - contracts / users / trips counts (those modules don't exist yet; the
 *    API reports 0 until they do).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('sequence');
            $table->string('code', 24);
            $table->string('name');
            $table->string('operational_region')->nullable();

            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('location_url', 2048)->nullable();

            $table->enum('operational_status', ['active', 'deactivated'])->default('active');
            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['company_id', 'sequence']);
            $table->index('operational_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
