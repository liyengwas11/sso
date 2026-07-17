<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * GET /api/admin/roles
     * Lists every role with its permissions attached — this is the
     * source of truth an admin UI would render as an editable table.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-roles', $request->user());

        return response()->json(
            Role::with('permissions:id,name')->get(['id', 'name'])
        );
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorize('manage-roles', $request->user());

        return response()->json($role->load('permissions:id,name'));
    }

    /**
     * POST /api/admin/roles
     * Create a brand-new role with an arbitrary permission set. No
     * code changes needed to introduce e.g. "Manager" or "Supervisor"
     * later — this is exactly what makes the RBAC customizable.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create(['name' => $request->validated('name')]);

        if ($permissions = $request->validated('permissions')) {
            $role->syncPermissions($permissions);
        }

        return response()->json($role->load('permissions:id,name'), 201);
    }

    /**
     * PATCH /api/admin/roles/{role}
     * Rename a role and/or change which permissions it carries.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($name = $request->validated('name')) {
            $role->update(['name' => $name]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return response()->json($role->fresh()->load('permissions:id,name'));
    }

    /**
     * DELETE /api/admin/roles/{role}
     * Blocks deleting the 'admin' role specifically, so an admin can't
     * accidentally lock every admin out of the system. Everything else
     * is freely removable — reassign affected users first if needed.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('manage-roles', $request->user());

        if ($role->name === 'admin') {
            return response()->json([
                'message' => 'The admin role can\'t be deleted — create a replacement role and reassign users first.',
            ], 422);
        }

        $role->delete();

        return response()->json(null, 204);
    }
}
