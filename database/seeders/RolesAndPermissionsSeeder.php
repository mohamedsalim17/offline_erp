<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'system-settings.view',
            'system-settings.update',
            'backups.view',
            'backups.create',
            'backups.upload',
            'backups.delete',
            'backups.restore',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'warehouses.view',
            'warehouses.create',
            'warehouses.update',
            'warehouses.delete',
            'sales.view',
            'sales.create',
            'sales.view-all',
            'sales.settings',
            'reports.view',
            'reports.export',
            'finance.view',
            'finance.manage',
            'treasuries.view',
            'treasuries.manage',
            'stock-movements.view',
            'stock-movements.create',
            'stock-movements.approve',
            'stocktakes.view',
            'stocktakes.create',
            'stocktakes.approve',
        ];

        $permissions = collect($permissionNames)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions->reject(
            fn (Permission $permission) => $permission->name === 'roles.delete',
        ));

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions($permissions->filter(
            fn (Permission $permission) => in_array($permission->name, [
                'system-settings.view',
                'backups.view',
                'backups.create',
                'users.view',
                'products.view',
                'products.create',
                'products.update',
                'categories.view',
                'categories.create',
                'categories.update',
                'warehouses.view',
                'warehouses.create',
                'warehouses.update',
                'sales.view',
                'sales.create',
                'sales.settings',
                'system-settings.update',
                'reports.view',
                'reports.export',
                'finance.view',
                'finance.manage',
                'treasuries.view',
                'treasuries.manage',
                'stock-movements.view',
                'stock-movements.create',
                'stock-movements.approve',
                'stocktakes.view',
                'stocktakes.create',
                'stocktakes.approve',
            ], true),
        ));

        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions($permissions->filter(
            fn (Permission $permission) => in_array($permission->name, [
                'products.view',
                'categories.view',
                'warehouses.view',
            ], true),
        ));

        $this->call(ChartOfAccountsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
