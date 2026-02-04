<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    /**
     * Seed roles and some initial users.
     */
    public function run(): void
    {
        // 1) Create base roles
        $roleNames = [
            'admin',
            'manager',
            'owner',
            'agent',
            'driver',
            'customer',
        ];

        foreach ($roleNames as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        // 2) Default Super Admin
        if (! User::where('email', 'admin@smartcar.test')->exists()) {
            $admin = User::create([
                'name'     => 'Super Admin',
                'email'    => 'admin@smartcar.test',
                'phone'    => '0780000000',
                'role'     => 'admin',                 // 👈 important
                'password' => Hash::make('password'),
            ]);

            $admin->assignRole('admin');
        }

        // 3) Default Owner
        if (! User::where('email', 'owner@smartcar.test')->exists()) {
            $owner = User::create([
                'name'     => 'Demo Owner',
                'email'    => 'owner@smartcar.test',
                'phone'    => '0781112223',
                'role'     => 'owner',                 // 👈 important
                'password' => Hash::make('password'),
            ]);

            $owner->assignRole('owner');
        }
    }
}
