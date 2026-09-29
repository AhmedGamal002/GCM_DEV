<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD V01.14 §1.7.3 "Insert an asset into a project": a container / tank
 * left at a project site is out of the available pool. `project_id` is
 * that placement — null means the asset is in the pool. (Taking an asset
 * back out belongs with trips, a later phase; the FRD only defines the
 * insertion.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('contractor_id')
                ->constrained('projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
