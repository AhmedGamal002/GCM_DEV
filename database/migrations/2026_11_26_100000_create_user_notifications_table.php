<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (FRD V01.14 "الاشعارات والايميلات التلقائية", part 8.2): one row per recipient — a trip's
 * change of state or a purchase-order line running out. `type` says which and `data` holds what the sentence needs
 * (trip code, PO number, service…), so the text is written in the reader's language when it is read, never stored.
 * `subject` repeats the trip code / PO number as a plain column for the page's search box.
 *
 * `purchase_order_services.alert_status` remembers the last status a line was notified about, so the alert fires
 * once per change of status — whether a write caused it or the calendar did (the daily check).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 48);
            $table->foreignId('trip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject', 64)->nullable();
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('purchase_order_services', function (Blueprint $table) {
            $table->string('alert_status', 32)->nullable()->after('alert_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_services', function (Blueprint $table) {
            $table->dropColumn('alert_status');
        });

        Schema::dropIfExists('user_notifications');
    }
};
