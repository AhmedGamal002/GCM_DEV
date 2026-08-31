<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every vehicle document from the FRD §1.5.3 form:
 * registration card, fitness document, inspection certificate and
 * insurance are one-per-vehicle (enforced in the FormRequest); truck
 * entry permits are repeatable and additionally carry an area name.
 *
 * Attachments are stored on the private `local` disk and served only
 * through a Gate-checked download endpoint — never a public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'registration_card',
                'fitness_document',
                'inspection_certificate',
                'insurance',
                'entry_permit',
            ]);
            $table->string('document_number');
            $table->string('area_name')->nullable();
            $table->date('valid_to');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
