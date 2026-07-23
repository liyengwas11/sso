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
  
    public function index(Request $request): Response
    {
        $this->authorize('manage-events', $request->user());

        return Inertia::render('Admin/Flags/Index', [
            'flags' => EventEntryLog::with(['eventPass.attendee', 'eventPass.event', 'scannedBy:id,name'])
                ->flagged()
                ->when($request->query('status', 'unreviewed') === 'unreviewed', fn ($q) => $q->unreviewed())
                ->latest('scanned_at')
                ->paginate(25)
                ->withQueryString(),
            'statusFilter' => $request->query('status', 'unreviewed'),
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
