<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same "الرقم التعريفي" (ID) pattern as `companies` (and `users.code`
 * before it): `code` = the facility's own `prefix` + a zero-padded number
 * (e.g. ALF-0001), where the number (`sequence`) is a running counter per
 * tenant, assigned in IntermediateFacility::booted(). Stored rather than
 * computed so it can be searched and sorted, and never drifts even though
 * the prefix itself never changes after creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intermediate_facilities', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable()->after('id');
            $table->string('code', 16)->nullable()->after('sequence');
        });

        // Backfill any facility created before this column existed — same
        // per-tenant counter the model now assigns on creation, so the
        // sequence stays contiguous even if this migration runs against a
        // branch that already has real facility rows.
        DB::table('intermediate_facilities')
            ->select('id', 'tenant_id', 'prefix')
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->each(function ($rows) {
                $sequence = 0;
                foreach ($rows as $row) {
                    $sequence++;
                    DB::table('intermediate_facilities')->where('id', $row->id)->update([
                        'sequence' => $sequence,
                        'code' => sprintf('%s-%04d', $row->prefix, $sequence),
                    ]);
                }
            });

        Schema::table('intermediate_facilities', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable(false)->change();
            $table->string('code', 16)->nullable(false)->change();
            $table->unique(['tenant_id', 'sequence']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('intermediate_facilities', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'sequence']);
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropColumn(['sequence', 'code']);
        });
    }
};
