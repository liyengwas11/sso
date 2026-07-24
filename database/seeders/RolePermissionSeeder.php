<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Sensible starting point only — roles/permissions are managed at
     * runtime through Admin\RoleController afterward, nothing here is
     * hardcoded into route middleware.
     */
    public function run(): void
    {
        $permissions = [
            'manage-events',      // create/edit events, manage attendees & passes, view reports, manage staff
            'manage-users',       // create staff/gate-staff login accounts
            'manage-roles',       // customise roles & permissions
            'scan-event-entry',   // operate the gate scanner
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions); // admin starts with everything

        // Event-day gate staff get just enough to scan — nothing else.
        // Customisable like any role afterward via Admin\RoleController.
        $gateStaff = Role::firstOrCreate(['name' => 'gate-staff']);
        $gateStaff->syncPermissions(['scan-event-entry']);
    }
}
