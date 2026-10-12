<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vehicle category carries the embedded-container values most vehicles of
 * that category share (client request, outside the FRD): has an embedded
 * container or not, container vs tank, and which capacity. The create-vehicle
 * form pre-fills them as soon as the category is picked; they stay editable
 * on the vehicle. They are only a starting point — nothing on an existing
 * vehicle changes when a category's defaults do.
 *
 * Every existing category starts as "no embedded container", i.e. exactly
 * today's behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->boolean('default_has_embedded_container')->default(false)->after('name_ar');
            $table->enum('default_embedded_container_type', ['container', 'tank'])->nullable()->after('default_has_embedded_container');
            $table->unsignedBigInteger('default_embedded_asset_capacity_category_id')->nullable()->after('default_embedded_container_type');

            $table->foreign('default_embedded_asset_capacity_category_id', 'vehicle_categories_default_capacity_foreign')
                ->references('id')->on('asset_capacity_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropForeign('vehicle_categories_default_capacity_foreign');
            $table->dropColumn([
                'default_has_embedded_container',
                'default_embedded_container_type',
                'default_embedded_asset_capacity_category_id',
            ]);
        });
    }
};
