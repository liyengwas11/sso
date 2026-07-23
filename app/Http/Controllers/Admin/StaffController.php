<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * GET /admin/staff
     * Every account that can actually LOG IN — admins and gate staff.
     * Deliberately separate from Attendee (event guests, never log
     * in). Individual accounts here are what makes every scan
     * attributable to a specific person, per the anti-fraud design.
     */
    public function index(Request $request): Response
    {
        $this->authorize('manage-users', $request->user());

        return Inertia::render('Admin/Staff/Index', [
            'staff' => User::with('roles:id,name')->orderBy('name')->get(),
            'roles' => Role::all(['id', 'name']),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'status' => 'active',
        ]);

        $staff->syncRoles($request->validated('roles'));

        return redirect()->route('admin.staff.index')
            ->with('success', "{$staff->name} can now log in.");
    }

    public function destroy(Request $request, User $staffMember): RedirectResponse
    {
        $this->authorize('manage-users', $request->user());

        if ($staffMember->id === $request->user()->id) {
            return redirect()->route('admin.staff.index')
                ->with('error', "You can't remove your own account.");
        }

        $staffMember->delete();

        return redirect()->route('admin.staff.index')
            ->with('success', 'Account removed.');
    }
}
