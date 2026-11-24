<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 8.1 — the number of a trip's generated documents (decision د2): ONE
 * number per trip shared by its delivery note and waste manifest (DN-100001 /
 * M-100001), running per tenant from 100001 across every trip (not per client
 * company like the trip's own code). It is reserved the first time a document
 * of the trip is generated (د3) and never changes after — null until then.
 *
 * Not the paper documents' own numbers (delivery_note_number /
 * waste_manifest_number, decision د1): those are typed by staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('document_number')->nullable()->after('code');
            $table->unique(['tenant_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'document_number']);
            $table->dropColumn('document_number');
        });
    }
};
