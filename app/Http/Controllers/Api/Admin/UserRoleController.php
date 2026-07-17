<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUserRolesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    /**
     * PUT /api/admin/users/{user}/roles
     * Replaces a user's role set entirely (sync, not append) — the
     * request body is treated as the full desired state, which keeps
     * the admin UI's "checked roles" list a direct mirror of the API.
     */
    public function update(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        $user->syncRoles($request->validated('roles'));

        return response()->json($user->load('roles:id,name'));
    }
}
