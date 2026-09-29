<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client companies — FRD V01.14 §1.11. A company is a client GCM has a
 * contract with; its projects (§1.12) are the sites trips collect waste
 * from. `Company` here is the CLIENT company only — the operating
 * company is the `tenants` row and contractor companies (§1.10) are a
 * separate entity.
 *
 * `prefix` is the "الاسم المختصر": 3 English letters, unique per tenant,
 * used "as a mix with numbers to express a unique number belonging to the
 * company" — so it is locked after creation (numbers are built from it).
 *
 * `code` is the FRD's "الرقم التعريفي": the prefix + a zero-padded number,
 * e.g. ALN-0001. `sequence` is that number — one running counter per
 * tenant, assigned in Company::booted() — and `code` is stored (not
 * computed) so it can be searched and never shifts.
 *
 * Not here yet, on purpose:
 *  - the "client representative account" (needs users.company_id —
 *    added together with the client accounts, FRD §1.4);
 *  - projects / users / contracts counts (those modules don't exist yet;
 *    the API reports 0 until they do).
 *
 * The three attachments (contract, commercial registration, tax
 * registration) are stored on the private `local` disk and served
 * through an authorised download route; the logo is public, like user
 * photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('sequence');
            $table->string('code', 16);
            $table->string('name');
            $table->string('prefix', 3);
            $table->string('business_sector')->nullable();
            $table->string('logo')->nullable();

            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
