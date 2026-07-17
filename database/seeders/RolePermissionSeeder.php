<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Employees never log in under the current scan flow (identity is
     * verified per-scan via name + employment number, not a session),
     * so there's currently only one role that actually authenticates:
     * admin. This still seeds a full permission catalogue and keeps
     * the 'employee' role available for whenever a self-service
     * portal (view own hours, etc.) gets built — at that point it's
     * just a role edit through Admin\RoleController, no redeploy.
     */
    public function run(): void
    {
        $permissions = [
            'manage-qr-code',
            'view-all-attendance',
            'manage-users',
            'manage-roles',
            'view-own-attendance', // reserved for a future employee self-service portal
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'manage-qr-code',
            'view-all-attendance',
            'manage-users',
            'manage-roles',
        ]);

        $employee = Role::firstOrCreate(['name' => 'employee']);
        $employee->syncPermissions(['view-own-attendance']);
    }
}
