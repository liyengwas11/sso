<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /**
     * GET /api/admin/employees
     * The full registry, searchable/paginated — this is the admin's
     * management screen, distinct from the public-facing
     * AttendanceController::employees() used by the scan dropdown.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-users', $request->user());

        $employees = User::query()
            ->when($request->query('search'), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employment_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($employees);
    }

    public function show(Request $request, User $employee): JsonResponse
    {
        $this->authorize('manage-users', $request->user());

        return response()->json($employee->load('roles:id,name'));
    }

    /**
     * POST /api/admin/employees
     * Employees are registry entries first, login accounts second —
     * most will never log in anywhere, so we generate a random,
     * never-disclosed password rather than making an admin invent one.
     * (If self-service login is added later, wire up a reset-password
     * flow instead of trying to hand out this generated value.)
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = User::create([
            ...$request->validated(),
            'password' => Hash::make(Str::random(32)),
            'status' => 'active',
        ]);

        return response()->json($employee, 201);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json($employee->fresh());
    }

    /**
     * DELETE /api/admin/employees/{employee}
     * Suspends rather than hard-deletes — attendance_logs has a
     * foreign key to users, and disputes/reports need historical
     * records to stay intact even after someone leaves.
     */
    public function destroy(Request $request, User $employee): JsonResponse
    {
        $this->authorize('manage-users', $request->user());

        $employee->update(['status' => 'suspended']);

        return response()->json($employee->fresh());
    }
}
