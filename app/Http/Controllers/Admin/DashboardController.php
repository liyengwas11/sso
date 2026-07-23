<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventEntryLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $upcomingOrCurrent = Event::where('end_date', '>=', now()->toDateString())
            ->withCount('passes')
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'upcomingEvents' => Event::where('start_date', '>=', now()->toDateString())->count(),
                'checkedInToday' => EventEntryLog::whereDate('scanned_at', now()->toDateString())
                    ->whereIn('result', ['granted', 'override_granted'])
                    ->count(),
                'unreviewedFlags' => EventEntryLog::flagged()->unreviewed()->count(),
            ],
            'events' => $upcomingOrCurrent,
        ]);
    }
}
