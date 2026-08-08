<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUserRolesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    /**
     * PATCH /admin/users/{user}/roles
     * Replaces a user's role set entirely (sync, not append). Not a
     * page of its own — an action triggered from wherever staff
     * accounts are managed, redirecting back there afterward.
     */
    public function update(AssignUserRolesRequest $request, User $user): RedirectResponse
    {
        $user->syncRoles($request->validated('roles'));

        return redirect()->back()
            ->with('success', "Roles updated for {$user->name}.");
    }
}
