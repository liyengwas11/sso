<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanAttendanceRequest;
use App\Models\AttendanceLog;
use App\Models\AttendanceQrCode;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/attendance/employees?token=...&search=...
     * Feeds the Select2 dropdown on the scan page. Deliberately
     * requires a currently-active QR token, so the employee directory
     * (names only — nothing sensitive) is only reachable during a
     * live scan window rather than sitting open to the public.
     */
    public function employees(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $qrIsLive = AttendanceQrCode::where('token', $request->query('token'))
            ->active()
            ->exists();

        if (! $qrIsLive) {
            return response()->json(['message' => 'This code has expired.'], 422);
        }

        $employees = User::query()
            ->where('status', 'active')
            ->whereNotNull('employment_number')
            ->when($request->query('search'), fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(50) // Select2 paginates/searches server-side, so a full dump isn't needed
            ->get(['id', 'name']);

        return response()->json($employees);
    }

    /**
     * POST /api/attendance/scan
     * Public endpoint: an admin displays the QR, an employee scans it
     * on their own phone, picks their name, and types their
     * employment number. No login required. Expired/duplicate/
     * identity-mismatch cases are thrown as exceptions and rendered
     * by their own render() methods, so this method only handles the
     * happy path.
     */
    public function store(ScanAttendanceRequest $request): JsonResponse
    {
        $log = $this->attendanceService->processScan(
            $request->validated('token'),
            $request->validated('user_id'),
            $request->validated('employment_number'),
            $request
        );

        return response()->json([
            'success' => true,
            'type' => $log->type,
            'scanned_at' => $log->scanned_at->toIso8601String(),
            'message' => $this->confirmationMessage($log),
        ]);
    }

    private function confirmationMessage(AttendanceLog $log): string
    {
        $user = $log->user;
        $time = $log->scanned_at->timezone($user->timezone ?? config('app.timezone'))->format('g:i A');
        $action = $log->type === 'clock_in' ? 'Clocked in' : 'Clocked out';

        return "Welcome, {$user->name}. {$action} at {$time}.";
    }
}
