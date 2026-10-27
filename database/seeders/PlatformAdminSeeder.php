<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

/**
 * Dev-only Super Admin account. Not meant for production seeding.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        // The password below is public (it is in this file). Seeded on a live system it would be a Super Admin
        // account anybody could sign in to — create the real one with a password of your own instead.
        if (app()->isProduction()) {
            throw new \RuntimeException('PlatformAdminSeeder creates a Super Admin with a known password and must not run in production.');
        }

        PlatformAdmin::firstOrCreate(
            ['email' => 'superadmin@product.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password', // hashed via the model's cast; dev-only credential
            ]
        );
    }
}
