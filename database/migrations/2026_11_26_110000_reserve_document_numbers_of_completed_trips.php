<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The document number (decision د2) is now reserved when a trip is completed (CompleteTrip), no longer the first time
 * one of its documents is opened. This gives the trips completed before that change — and never opened — their
 * number, per tenant after the highest one already taken, in the order they were completed. Cancelled trips are left
 * alone (they have no documents, د3); one that already holds a number keeps it.
 *
 * Plain queries rather than TripDocumentNumber::reserve(): a migration runs without a bound tenant. Nothing is undone
 * on rollback — a reserved number is never given back.
 */
return new class extends Migration
{
    private const FIRST = 100001;

    public function up(): void
    {
        $tenants = DB::table('trips')
            ->where('status', 'completed')
            ->whereNull('document_number')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenants as $tenantId) {
            DB::transaction(function () use ($tenantId) {
                DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->value('id');

                $next = max(self::FIRST, ((int) DB::table('trips')->where('tenant_id', $tenantId)->lockForUpdate()->max('document_number')) + 1);

                $ids = DB::table('trips')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereNull('document_number')
                    ->orderBy('completed_at')
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($ids as $id) {
                    DB::table('trips')->where('id', $id)->update(['document_number' => $next++]);
                }
            });
        }
    }

    public function down(): void
    {
        // A reserved number is never given back (see TripDocumentNumber).
    }
};
