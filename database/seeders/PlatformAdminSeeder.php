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
        PlatformAdmin::firstOrCreate(
            ['email' => 'superadmin@product.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password', // hashed via the model's cast; dev-only credential
            ]
        );
    }
}
