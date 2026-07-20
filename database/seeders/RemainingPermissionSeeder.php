<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RemainingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',

            'view sale returns',
            'create sale returns',
            'complete sale returns',
            'cancel sale returns',

            'view purchase returns',
            'create purchase returns',
            'complete purchase returns',
            'cancel purchase returns',

            'view stock adjustments',
            'create stock adjustments',
            'complete stock adjustments',
            'cancel stock adjustments',

            'view payments',
            'create payments',

            'view reports',
            'view users',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $manager = Role::findOrCreate('manager', 'web');
        $staff = Role::findOrCreate('staff', 'web');

        $admin->givePermissionTo(Permission::query()->where('guard_name', 'web')->get());

        $manager->givePermissionTo([
            'view dashboard',
            'view sale returns',
            'create sale returns',
            'complete sale returns',
            'cancel sale returns',
            'view purchase returns',
            'create purchase returns',
            'complete purchase returns',
            'cancel purchase returns',
            'view stock adjustments',
            'create stock adjustments',
            'complete stock adjustments',
            'cancel stock adjustments',
            'view payments',
            'create payments',
            'view reports',
        ]);

        $staff->givePermissionTo([
            'view dashboard',
            'view sale returns',
            'create sale returns',
            'view purchase returns',
            'view payments',
            'create payments',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
