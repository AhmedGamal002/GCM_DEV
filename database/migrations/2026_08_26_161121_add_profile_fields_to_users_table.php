<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('photo')->nullable()->after('phone');
            $table->longText('additional_data')->nullable()->after('status');

            // Driver affiliation per the FRD: a driver either belongs to
            // GCM directly or to a Contractor. `contractor_id` has no FK
            // yet — Contractor doesn't exist until Week 5, same pattern
            // already used for vehicles/assets in WEEKLY_PLAN.md.
            $table->enum('affiliation', ['gcm', 'contractor'])->default('gcm')->after('additional_data');
            $table->unsignedBigInteger('contractor_id')->nullable()->after('affiliation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'photo', 'additional_data', 'affiliation', 'contractor_id']);
        });
    }
};
