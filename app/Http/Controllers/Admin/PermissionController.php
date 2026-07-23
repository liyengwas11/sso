<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * POST /admin/permissions
     * No dedicated index page — the permission catalogue is displayed
     * as part of Admin/Roles (RoleController::index), since that's
     * the only place it's actually used. Creating one here just makes
     * it available for the next role-edit without a redeploy.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create(['name' => $request->validated('name')]);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Permission created.');
    }

    public function destroy(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorize('manage-roles', $request->user());

        $permission->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Permission deleted.');
    }
}
