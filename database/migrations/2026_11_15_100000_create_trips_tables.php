<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trips — FRD V01.14 §1.14, Phase 3 part 7.1.
 *
 *  - trips: one trip to one project of one client company, ONE main
 *    service and at most one sub-service (the FRD: "واحدة فقط"). `code` is
 *    the company's short name + "T" + a number per company (ALN-T0001,
 *    decision ج14). The trip keeps its own cost (decision ب3: a later change
 *    of the PO's cost doesn't touch it) and points at the PO line it is
 *    charged to (`purchase_order_service_id`, null = "without PO"). Files
 *    (paper documents, live photo, the client's attachment, the signature /
 *    stamp snapshots of decisions د4–د6) live on the private disk.
 *  - trip_events: the trip's permanent, append-only history (decision ج8) —
 *    who created, approved, rejected, edited (field: from → to), completed
 *    or cancelled it, and why. GCM staff only.
 *  - trip_asset_movements: what the trip did to its containers when it was
 *    completed (decision ج3: the going container enters the project, the
 *    returning one leaves it), so cancelling — or editing the containers
 *    of — a completed trip can put them back.
 *
 * The FK from quota_ledger_entries.trip_id to trips comes in the next
 * migration (2026_11_15_100100).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->string('code', 24);
            $table->unsignedInteger('sequence');
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->dateTime('trip_at');

            $table->enum('status', ['awaiting_client_approval', 'client_approved', 'client_issue', 'completed', 'cancelled'])
                ->default('awaiting_client_approval');
            $table->enum('source', ['driver', 'staff']);
            $table->boolean('requires_client_approval')->default(true);
            $table->boolean('requires_purchase_order')->default(true);

            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('going_asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('returning_asset_id')->nullable()->constrained('assets')->restrictOnDelete();

            $table->foreignId('main_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('trip_cost', 12, 2)->nullable();
            $table->decimal('ancillary_cost', 12, 2)->default(0);

            // ج10: tonnes and m³, 3 decimals; null on a supplying trip (ج11).
            $table->decimal('quantity_ton', 12, 3)->nullable();
            $table->decimal('quantity_cbm', 12, 3)->nullable();
            $table->foreignId('intermediate_facility_id')->nullable()->constrained()->restrictOnDelete();

            // The paper documents' own numbers (د1 — not the generated DN-/M- numbers of 8.1) and photos.
            $table->string('delivery_note_number', 100)->nullable();
            $table->string('delivery_note_path')->nullable();
            $table->string('waste_manifest_number', 100)->nullable();
            $table->string('waste_manifest_path')->nullable();
            $table->string('recycle_receipt_number', 100)->nullable();
            $table->string('recycle_receipt_path')->nullable();

            $table->string('location_url', 2048)->nullable();
            $table->string('live_photo_path')->nullable();
            $table->string('other_attachment_path')->nullable();
            $table->longText('additional_data')->nullable();

            // The default approvers (notifications and reminders only — ج6).
            $table->foreignId('client_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gcm_approver_id')->nullable()->constrained('users')->nullOnDelete();

            // The client's review: comment / attachment (rejection reason or approval note).
            $table->text('client_comment')->nullable();
            $table->string('client_attachment_path')->nullable();
            $table->foreignId('client_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('client_approved_at')->nullable();

            // Snapshots (copies, not references to the account's files): the client's
            // signature and stamp (د6, or uploaded by GCM staff when the trip completes
            // without the client — د4) and GCM's representative's (the one who completed it, د5).
            $table->string('client_signer_name')->nullable();
            $table->string('client_signature_path')->nullable();
            $table->string('client_stamp_path')->nullable();
            $table->string('gcm_signature_path')->nullable();
            $table->string('gcm_stamp_path')->nullable();

            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['company_id', 'sequence']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'trip_at']);
            $table->index(['project_id', 'trip_at']);
            $table->index('driver_id');
            $table->index('vehicle_id');
            $table->index('main_service_id');
        });

        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();

            $table->string('type', 48);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['trip_id', 'id']);
        });

        Schema::create('trip_asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();

            $table->enum('role', ['going', 'returning']);
            $table->foreignId('from_project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('to_project_id')->nullable()->constrained('projects')->restrictOnDelete();
            // Set when the movement is undone (trip cancelled, or its containers edited);
            // `reversal_skipped` when the asset had moved since and was left where it was.
            $table->timestamp('reversed_at')->nullable();
            $table->boolean('reversal_skipped')->default(false);
            $table->timestamps();

            $table->index(['trip_id', 'reversed_at']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_asset_movements');
        Schema::dropIfExists('trip_events');
        Schema::dropIfExists('trips');
    }
};
