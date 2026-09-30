<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Creates default settings using your model's logic (no hardcoded ID)
        Setting::current();

        // ✅ Admin user
        User::firstOrCreate(
            ['email' => 'admin@gorav.click'],
            [
                'name'     => 'Gorav Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );
    }
}