<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Global scope roles
        Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'api'],
            [
                'name' => 'super_admin',
                'guard_name' => 'api',
                'scope' => 'global',
                'is_super_admin' => true,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        // Tenant scope roles
        Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api', 'scope' => 'tenant'],
            [
                'name' => 'admin',
                'guard_name' => 'api',
                'scope' => 'tenant',
                'is_super_admin' => false,
                'is_admin' => true,
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'api', 'scope' => 'tenant'],
            [
                'name' => 'manager',
                'guard_name' => 'api',
                'scope' => 'tenant',
                'is_super_admin' => false,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'technician', 'guard_name' => 'api', 'scope' => 'tenant'],
            [
                'name' => 'technician',
                'guard_name' => 'api',
                'scope' => 'tenant',
                'is_super_admin' => false,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'attendant', 'guard_name' => 'api', 'scope' => 'tenant'],
            [
                'name' => 'attendant',
                'guard_name' => 'api',
                'scope' => 'tenant',
                'is_super_admin' => false,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'finance', 'guard_name' => 'api', 'scope' => 'tenant'],
            [
                'name' => 'finance',
                'guard_name' => 'api',
                'scope' => 'tenant',
                'is_super_admin' => false,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Roles seeded successfully!');
    }
}
