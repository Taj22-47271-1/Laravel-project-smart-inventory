<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',

            'view products',
            'create products',
            'edit products',
            'delete products',

            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

            'view brands',
            'create brands',
            'edit brands',
            'delete brands',

            'view units',
            'create units',
            'edit units',
            'delete units',

            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',

            'view customers',
            'create customers',
            'edit customers',
            'delete customers',

            'view purchases',
            'create purchases',
            'edit purchases',
            'delete purchases',
            'approve purchases',

            'view sales',
            'create sales',
            'edit sales',
            'delete sales',

            'view inventory',
            'manage inventory',

            'view reports',

            'view users',
            'create users',
            'edit users',
            'delete users',

            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::where('name', 'admin')->firstOrFail();
        $manager = Role::where('name', 'manager')->firstOrFail();
        $staff = Role::where('name', 'staff')->firstOrFail();

        // Admin সব permissions পাবে।
        $admin->syncPermissions(Permission::all());

        // Manager permissions.
        $manager->syncPermissions([
            'view dashboard',

            'view products',
            'create products',
            'edit products',

            'view categories',
            'create categories',
            'edit categories',

            'view brands',
            'create brands',
            'edit brands',

            'view units',
            'create units',
            'edit units',

            'view suppliers',
            'create suppliers',
            'edit suppliers',

            'view customers',
            'create customers',
            'edit customers',

            'view purchases',
            'create purchases',
            'edit purchases',
            'approve purchases',

            'view sales',
            'create sales',
            'edit sales',

            'view inventory',
            'manage inventory',

            'view reports',
        ]);

        // Staff permissions.
        $staff->syncPermissions([
            'view dashboard',

            'view products',

            'view categories',

            'view brands',

            'view units',

            'view suppliers',

            'view customers',
            'create customers',
            'edit customers',

            'view purchases',
            'create purchases',

            'view sales',
            'create sales',

            'view inventory',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}