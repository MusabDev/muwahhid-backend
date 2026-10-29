<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@muwahhid.net')],
            ['name' => 'Admin', 'password' => env('ADMIN_PASSWORD', 'password')],
        )->forceFill(['is_admin' => true])->save();

        $this->call(CategorySeeder::class);

        if (app()->isLocal()) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
