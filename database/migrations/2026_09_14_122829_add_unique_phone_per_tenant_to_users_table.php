<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone number must not repeat within the same tenant (same rule as
 * `users.code` and vehicles' plate — see StoreUserRequest/UpdateUserRequest/
 * StoreDriverRequest/UpdateDriverRequest for the app-level Rule::unique
 * this backs). Scoped by tenant, not global, since phone isn't a login
 * identifier the way email is (LoginController never looks it up).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'phone']);
        });
    }
};
