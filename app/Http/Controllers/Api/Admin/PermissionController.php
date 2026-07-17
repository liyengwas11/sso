<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * GET /api/admin/permissions
     * The full catalogue of permissions in the system, for an admin
     * UI to offer when building/editing a role.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-roles', $request->user());

        return response()->json(Permission::all(['id', 'name']));
    }

    /**
     * POST /api/admin/permissions
     * Define a brand-new permission. Spatie registers it as a Laravel
     * Gate automatically, so it's immediately usable in
     * $user->can('new-permission') without touching route/middleware
     * code — this is what makes the whole RBAC layer extensible
     * without redeploying.
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = Permission::create(['name' => $request->validated('name')]);

        return response()->json($permission, 201);
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->authorize('manage-roles', $request->user());

        $permission->delete();

        return response()->json(null, 204);
    }
}
