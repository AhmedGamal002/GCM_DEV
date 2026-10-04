<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD V01.14 §1.10.2 "حساب ممثل المتعهد": the optional representative of a
 * contractor — one of the contractor's own user accounts (role
 * contractor_user). It only exists once those accounts do, so it is chosen
 * on the contractor's edit page, same as a client company's representative.
 * nullOnDelete: accounts are never hard-deleted, but if one ever is the
 * contractor simply has no representative any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->foreignId('representative_id')->nullable()->after('trading_name')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('representative_id');
        });
    }
};
