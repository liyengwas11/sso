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

        $logs = EventEntryLog::with(['eventPass.attendee', 'eventDay', 'scannedBy:id,name'])
            ->whereHas('eventPass', fn ($q) => $q->where('event_id', $event->id))
            ->when($flaggedOnly, fn ($q) => $q->flagged())
            ->latest('scanned_at')
            ->paginate(30)
            ->withQueryString();

        $totalPasses = $event->passes()->count();
        $checkedIn = $event->passes()
            ->whereHas('entryLogs', fn ($q) => $q->whereIn('result', ['granted', 'override_granted']))
            ->count();

        return Inertia::render('Admin/Events/Reports', [
            'event' => $event,
            'logs' => $logs,
            'flaggedOnly' => $flaggedOnly,
            'summary' => [
                'totalPasses' => $totalPasses,
                'checkedIn' => $checkedIn,
                'notCheckedIn' => $totalPasses - $checkedIn,
                'flaggedCount' => EventEntryLog::whereHas('eventPass', fn ($q) => $q->where('event_id', $event->id))->flagged()->count(),
            ],
        ]);
    }
}
