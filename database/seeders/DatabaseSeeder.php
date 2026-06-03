<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'superadmin'],
            [
                'name' => 'Super Admin Local',
                'password' => bcrypt(env('LOCAL_ADMIN_PASSWORD', 'admin987')),
                'role' => 'superadmin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'web.hrm@hartonomotor.com'],
            [
                'name' => 'Super Admin Live',
                'password' => bcrypt(env('PROD_ADMIN_PASSWORD', 'admin987')),
                'role' => 'superadmin',
            ]
        );
    }
}
