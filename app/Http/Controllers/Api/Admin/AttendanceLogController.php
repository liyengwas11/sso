<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    /**
     * GET /api/admin/attendance/logs
     * All employees, filterable by user/date range.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-all-attendance', $request->user());

        $logs = AttendanceLog::with('user:id,name,email')
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('scanned_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('scanned_at', '<=', $to))
            ->when($request->query('type'), fn ($q, $type) => $q->ofType($type))
            ->latest('scanned_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($logs);
    }

    /**
     * GET /api/admin/attendance/logs/live
     * Today's feed, most recent first — designed to be polled from
     * an admin dashboard (Inertia 2's built-in polling, Phase 2)
     * rather than needing websockets for v1.
     */
    public function live(Request $request): JsonResponse
    {
        $this->authorize('view-all-attendance', $request->user());

        $logs = AttendanceLog::with('user:id,name,email')
            ->today()
            ->latest('scanned_at')
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }
}
