<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD: every details/edit page shows "اخر تحديث: تم بواسطة X – التاريخ والوقت"
 * (Last updated by X — <datetime>). `assets` and `asset_capacity_categories`
 * already carry `updated_by`; this retro-fits the same column onto the
 * three older modules so their view/edit pages can show the same line.
 *
 * `updated_at` itself already exists (standard timestamps) — only the
 * "by whom" was missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'drivers', 'vehicles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'drivers', 'vehicles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
