<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkInService) {}

    /**
     * GET /admin/events/{event}/scan/{type?}
     * Shows the appropriate scanner based on type parameter.
     * Defaults to 'check-in' if not specified.
     */
    public function show(Request $request, Event $event, string $type = 'check-in'): Response
    {
        $this->authorize('scan-event-entry', $request->user());

        if (!in_array($type, ['check-in', 'check-out'])) {
            abort(404, 'Invalid scan type.');
        }

        $today = $event->days()->whereDate('date', now()->toDateString())->first();
        $gates = config('event.gates', [
            'Main Entrance',
            'VIP Entrance',
            'Side Gate',
            'Back Entrance',
        ]);

        return Inertia::render('Admin/Events/Scan', [
            'event' => $event,
            'today' => $today,
            'type' => $type,
            'gates' => $gates,
            'title' => $type === 'check-in' ? 'Check-In Scanner' : 'Check-Out Scanner',
            'scannerConfig' => [
                'buttonText' => $type === 'check-in' ? 'Check In' : 'Check Out',
                'successColor' => $type === 'check-in' ? 'green' : 'blue',
                'successIcon' => $type === 'check-in' ? 'check-in' : 'check-out',
            ],
        ]);
    }

    /**
     * POST /admin/events/{event}/scan/{type?}
     * Processes a scan attempt.
     */
    public function store(Request $request, Event $event, string $type = 'check-in'): JsonResponse
    {
        $this->authorize('scan-event-entry', $request->user());

        // Validate type
        if (!in_array($type, ['check-in', 'check-out'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid scan type.',
            ], 422);
        }

        // Validate request
        $request->validate([
            'token' => ['required', 'string'],
            'override' => ['sometimes', 'boolean'],
            'gate_location' => ['nullable', 'string', 'max:255'],
        ]);

        // Get today's event day
        $today = $event->days()->whereDate('date', now()->toDateString())->first();

        if (!$today) {
            return response()->json([
                'status' => 'not_event_day',
                'message' => 'This event is not running today.',
                'attendee' => null,
            ]);
        }

        // Process the scan
        $result = $this->checkInService->processScan(
            event: $event,
            token: $request->string('token'),
            staff: $request->user(),
            request: $request,
            override: $request->boolean('override'),
            type: $type,
            eventDay: $today
        );

        $pass = $result['pass'];

        // Build the response
        $response = [
            'status' => $result['status'],
            'message' => $result['message'],
            'current_status' => $result['current_status'] ?? null,
        ];

        // Include attendee data if pass exists
        if ($pass) {
            $response['attendee'] = [
                'name' => $pass->attendee->name,
                'organisation' => $pass->attendee->organisation,
                'role_title' => $pass->attendee->role_title,
                'photo_url' => $pass->attendee->photoUrl(), // This calls the method we added
                'email' => $pass->attendee->email,
            ];
        }

        // Include original check-in data for duplicate attempts
        if (isset($result['original_check_in'])) {
            $response['original_check_in'] = $result['original_check_in'];
        }

        // Determine HTTP status code
        $httpStatus = match ($result['status']) {
            'granted' => 200,
            'already_checked_in', 'not_checked_in', 'already_checked_out', 'not_accredited_today' => 409,
            'invalid', 'wrong_event', 'revoked' => 404,
            'not_event_day' => 422,
            default => 400,
        };

        return response()->json($response, $httpStatus);
    }
}
