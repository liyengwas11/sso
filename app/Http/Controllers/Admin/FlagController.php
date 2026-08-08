<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventEntryLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlagController extends Controller
{

    public function index(Request $request)
    {
        $status = $request->query('status', 'unreviewed');

        $flags = EventEntryLog::with([
            'eventPass.attendee',
            'eventPass.attendee.media',
            'eventPass.event',
            'scannedBy:id,name'
        ])
            ->flagged()
            ->when($status === 'unreviewed', fn($q) => $q->unreviewed())
            ->latest('scanned_at')
            ->paginate(25)
            ->withQueryString();
// dd($flags->first()->eventPass->attendee);
        // Add a count of unreviewed flags for the badge
        $unreviewedCount = EventEntryLog::flagged()->unreviewed()->count();
        $totalCount = EventEntryLog::flagged()->count();

        return Inertia::render('Admin/Flags/Index', [
            'flags' => $flags,
            'statusFilter' => $status,
            'unreviewedCount' => $unreviewedCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function markReviewed(Request $request, EventEntryLog $flag): RedirectResponse
    {
        $this->authorize('manage-events', $request->user());

        $flag->update([
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Marked reviewed.');
    }
}
