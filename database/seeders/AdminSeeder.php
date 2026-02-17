<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the 'admin' role exists (Spatie)
        $adminRole = Role::firstOrCreate([
            'name'       => 'admin',
            'guard_name' => 'web',
        ]);

        // Create or update the admin user
        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'     => 'System Admin',
                'phone'    => '0788888888',
                'role'     => 'admin',                 
                'password' => Hash::make('admin@123'),
            ]
        );

        if (! $user->hasRole('admin')) {
            $user->assignRole($adminRole);
        }
    }
}
