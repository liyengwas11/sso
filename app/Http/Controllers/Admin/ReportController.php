<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventEntryLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('manage-events', $request->user());

        $flaggedOnly = $request->boolean('flagged_only');

        // Load all necessary relationships
        $logs = EventEntryLog::with([
            'eventPass',
            'eventPass.attendee',
            'eventPass.attendee.media', // Load media for photos
            'eventDay',
            'scannedBy:id,name'
        ])
            ->whereHas('eventPass', fn($q) => $q->where('event_id', $event->id))
            ->when($flaggedOnly, fn($q) => $q->flagged())
            ->latest('scanned_at')
            ->paginate(30)
            ->withQueryString();

        // Calculate summary stats
        $totalPasses = $event->passes()->count();

        // Checked in (at least one successful check-in)
        $checkedIn = $event->passes()
            ->whereHas('entryLogs', function ($q) {
                $q->whereIn('result', ['granted', 'override_granted'])
                    ->where('type', 'check-in');
            })
            ->count();

        // Checked out (latest successful log is check-out)
        $checkedOut = $event->passes()
            ->whereHas('entryLogs', function ($q) {
                $q->whereIn('result', ['granted', 'override_granted'])
                    ->where('type', 'check-out')
                    ->whereRaw('scanned_at = (SELECT MAX(scanned_at) FROM event_entry_logs e2
                               WHERE e2.event_pass_id = event_entry_logs.event_pass_id
                               AND e2.result IN ("granted", "override_granted"))');
            })
            ->count();

        // Currently inside = checked in but NOT checked out
        $currentlyInside = $event->passes()
            ->whereHas('entryLogs', function ($q) {
                $q->whereIn('result', ['granted', 'override_granted'])
                    ->where('type', 'check-in')
                    ->whereRaw('scanned_at = (SELECT MAX(scanned_at) FROM event_entry_logs e2
                               WHERE e2.event_pass_id = event_entry_logs.event_pass_id
                               AND e2.result IN ("granted", "override_granted"))');
            })
            ->count();

        $flaggedCount = EventEntryLog::whereHas('eventPass', fn($q) => $q->where('event_id', $event->id))
            ->flagged()
            ->count();

        return Inertia::render('Admin/Events/Reports', [
            'event' => $event,
            'logs' => $logs,
            'flaggedOnly' => $flaggedOnly,
            'summary' => [
                'totalPasses' => $totalPasses,
                'checkedIn' => $checkedIn,
                'checkedOut' => $checkedOut,
                'currentlyInside' => $currentlyInside,
                'notCheckedIn' => $totalPasses - $checkedIn,
                'flaggedCount' => $flaggedCount,
            ],
        ]);
    }
}
