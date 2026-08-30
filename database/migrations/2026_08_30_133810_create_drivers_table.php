<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // No FK yet — the `contractors` table doesn't exist until
            // Week 5 (same rule as vehicles/assets, see WEEKLY_PLAN.md).
            // Always null for now: only GCM-affiliated drivers are
            // creatable until the Contractor branch lands.
            $table->unsignedBigInteger('contractor_id')->nullable();

            $table->string('residence_number')->nullable();
            $table->date('residence_valid_to')->nullable();
            $table->string('residence_attachment')->nullable();

            $table->string('license_number')->nullable();
            $table->date('license_valid_to')->nullable();
            $table->string('license_attachment')->nullable();

            $table->string('operational_license_number')->nullable();
            $table->date('operational_license_valid_to')->nullable();
            $table->string('operational_license_attachment')->nullable();

            $table->string('insurance_number')->nullable();
            $table->date('insurance_valid_to')->nullable();
            $table->string('insurance_attachment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
