<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::create([
            'facility_id' => null, // or set to a specific facility ID
            'department_id' => null, // or set to a specific department ID
            'cadre_id' => null, // or set to a specific cadre ID
            'role' => 'admin', // or keep default 'mentee'
            'first_name' => 'Super',
            'middle_name' => null,
            'last_name' => 'Admin',
            'email' => 'admin@super.com',
            'id_number' => 'ADMIN001', // unique ID number
            'phone' => '+1234567890',
            'status' => 'active',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create super_admin role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web'
        ]);

        // Assign role to user
        $superAdmin->assignRole($role);
    }
}
