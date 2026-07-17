<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanAttendanceRequest;
use App\Models\AttendanceLog;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    /**
     * POST /api/attendance/scan
     * The core kiosk-scan endpoint. Expired/duplicate cases are thrown
     * as exceptions and rendered by their own render() methods, so this
     * method only needs to handle the happy path.
     */
    public function store(ScanAttendanceRequest $request): JsonResponse
    {
        $log = $this->attendanceService->processScan(
            $request->user(),
            $request->validated('token'),
            $request
        );

        return response()->json([
            'success' => true,
            'type' => $log->type,
            'scanned_at' => $log->scanned_at->toIso8601String(),
            'message' => $this->confirmationMessage($request->user()->name, $log),
        ]);
    }

    /**
     * GET /api/attendance/history
     * The signed-in user's own attendance log, paginated.
     */
    public function history(Request $request): JsonResponse
    {
        $logs = $this->attendanceService->historyForUser(
            $request->user(),
            $request->query('from'),
            $request->query('to'),
        );

        return response()->json($logs);
    }

    private function confirmationMessage(string $name, AttendanceLog $log): string
    {
        $time = $log->scanned_at->timezone($log->user->timezone ?? config('app.timezone'))->format('g:i A');
        $action = $log->type === 'clock_in' ? 'Clocked in' : 'Clocked out';

        return "Welcome, {$name}. {$action} at {$time}.";
    }
}
