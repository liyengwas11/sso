<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * GET /admin/roles
     * The single role & permission management screen (FR-3.6.5).
     * Permissions are included as a prop here rather than served from
     * a separate page, since they're only ever edited in the context
     * of building/editing a role.
     */
    public function index(Request $request): Response
    {
        $this->authorize('manage-roles', $request->user());

        return Inertia::render('Admin/Roles', [
            'roles' => Role::with('permissions:id,name')->get(['id', 'name']),
            'permissions' => Permission::all(['id', 'name']),
        ]);
    }

    /**
     * POST /admin/roles
     * Create a role with an arbitrary permission set — no code
     * changes needed to introduce a new role later.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name')]);

        if ($permissions = $request->validated('permissions')) {
            $role->syncPermissions($permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', "Role \"{$role->name}\" created.");
    }

    /**
     * PATCH /admin/roles/{role}
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($name = $request->validated('name')) {
            $role->update(['name' => $name]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return redirect()->route('admin.roles.index')
            ->with('success', "Role \"{$role->name}\" updated.");
    }

    /**
     * DELETE /admin/roles/{role}
     * Blocks deleting the 'admin' role so an admin can't accidentally
     * lock every admin out of the system.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('manage-roles', $request->user());

        if ($role->name === 'admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'The admin role can\'t be deleted — create a replacement role and reassign users first.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', "Role \"{$role->name}\" deleted.");
    }
}
