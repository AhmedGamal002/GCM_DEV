<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intermediate waste facilities — FRD V01.14 §1.8. A facility performs
 * exactly ONE environmental service (safe disposal / sewage treatment /
 * recycling) and is a component of both a sub-service and a trip.
 *
 * `prefix` is the 3-letter unique short name the FRD asks for; unique per
 * tenant, never editable after creation.
 *
 * `recycling_efficiency` (a percentage) only applies to `recycle`
 * facilities: of every trip's load, this share is booked as recycled and
 * the rest as landfilled in the diversion report.
 *
 * Contract fields are all optional; the attachment is a private file
 * (disk `local`, served through a gated download endpoint), the logo is
 * public (disk `public`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intermediate_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->char('prefix', 3);
            $table->string('logo')->nullable();

            $table->enum('environmental_service', ['disposal', 'sewage_treatment', 'recycle']);
            $table->decimal('recycling_efficiency', 5, 2)->nullable();

            $table->enum('operational_status', ['active', 'deactivated'])->default('active');

            $table->string('address')->nullable();
            $table->string('location_url', 2048)->nullable();

            // A string, not an integer: contract numbers keep leading zeros.
            $table->string('contract_number')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('contract_attachment_path')->nullable();

            $table->longText('additional_data')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'prefix']);
            $table->index('tenant_id');
            $table->index('environmental_service');
            $table->index('operational_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intermediate_facilities');
    }
};
