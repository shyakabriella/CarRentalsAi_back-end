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

       
    }
}
