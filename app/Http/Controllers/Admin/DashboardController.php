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
            ->with(['days', 'media']) // Load media for images
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->description,
                    'venue' => $event->venue,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'is_recurring' => $event->is_recurring,
                    'passes_count' => $event->passes_count,
                    'days' => $event->days,
                    'cover_url' => $event->coverUrl(), // Add cover URL
                ];
            });

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
