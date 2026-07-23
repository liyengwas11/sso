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
    public function __construct(private readonly CheckInService $checkInService)
    {
    }

    /**
     * GET /admin/events/{event}/scan
     * The mobile-responsive page gate staff open on their own phone.
     * Authenticated (not the public/anonymous model the daily-office
     * scan flow uses) — every scan needs to be attributable to a
     * staff member, which is itself part of the anti-fraud design.
     */
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('scan-event-entry', $request->user());

        $today = $event->days()->whereDate('date', now()->toDateString())->first();

        return Inertia::render('Admin/Events/Scan', [
            'event' => $event,
            'today' => $today,
        ]);
    }

    /**
     * POST /admin/events/{event}/scan
     * { token, override? } -> structured grant/deny result including
     * the attendee's identity, so the UI can always show a photo for
     * staff to visually verify regardless of outcome.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('scan-event-entry', $request->user());

        $request->validate([
            'token' => ['required', 'string'],
            'override' => ['sometimes', 'boolean'],
        ]);

        $result = $this->checkInService->processScan(
            $event,
            $request->string('token'),
            $request->user(),
            $request,
            $request->boolean('override')
        );

        $pass = $result['pass'];

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'attendee' => $pass ? [
                'name' => $pass->attendee->name,
                'organisation' => $pass->attendee->organisation,
                'role_title' => $pass->attendee->role_title,
                'photo_url' => $pass->attendee->photoUrl(),
            ] : null,
        ]);
    }
}
