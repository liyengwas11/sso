<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportAttendeesRequest;
use App\Http\Requests\StoreAttendeeRequest;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\PassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendeeController extends Controller
{
    public function __construct(private readonly PassService $passService) {}

    public function index(Request $request, Event $event): Response
    {
        $this->authorize('manage-events', $request->user());

        // Get today's event day for status checking
        $today = $event->days()->whereDate('date', now()->toDateString())->first();

        $passes = EventPass::with([
            'attendee',
            'attendee.media',
            'days',
            'entryLogs' => fn($q) => $q->latest('scanned_at')->limit(1)
        ])
            ->where('event_id', $event->id)
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('attendee', fn($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('organisation', 'like', "%{$search}%"));
            })
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Transform passes to include current status
        $passes->getCollection()->transform(function ($pass) use ($today) {
            $pass->current_status = $today ? $pass->getCurrentStatusForDay($today) : 'not_checked_in';
            return $pass;
        });

        // Calculate event status
        $eventStatus = $this->getEventStatus($event);

        return Inertia::render('Admin/Events/Attendees', [
            'event' => [
                ...$event->toArray(),
                'days' => $event->days,
                'status' => $eventStatus,
                'status_label' => $this->getEventStatusLabel($eventStatus),
            ],
            'passes' => $passes,
            'filters' => $request->only('search'),
            'today' => $today,
        ]);
    }

    /**
     * Get the event status based on dates
     */
    private function getEventStatus(Event $event): string
    {
        $now = now()->startOfDay();
        $start = $event->start_date->startOfDay();
        $end = $event->end_date->startOfDay();

        if ($now > $end) {
            return 'ended';
        } elseif ($now >= $start && $now <= $end) {
            return 'active';
        } else {
            return 'upcoming';
        }
    }

    /**
     * Get the event status label
     */
    private function getEventStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'ended' => 'Ended',
            'upcoming' => 'Upcoming',
            default => 'Unknown',
        };
    }

    public function store(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        $attendee = Attendee::create($request->validatedAttendeeData());

        if ($request->hasPhoto()) {
            $attendee->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        $this->passService->issuePass($event, $attendee, $request->validatedDays());

        return redirect()->route('admin.events.attendees.index', $event)
            ->with('success', "{$attendee->name} added and issued a pass.");
    }

    public function update(StoreAttendeeRequest $request, Event $event, Attendee $attendee): RedirectResponse
    {
        $attendee->update($request->validatedAttendeeData());

        if ($request->hasPhoto()) {
            $attendee->clearMediaCollection('photo');
            $attendee->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        $pass = EventPass::where('event_id', $event->id)
            ->where('attendee_id', $attendee->id)
            ->first();

        if ($pass) {
            $days = $request->filled('days')
                ? $event->days()->whereIn('day_number', $request->input('days'))->get()
                : $event->days;

            $pass->days()->sync($days->pluck('id'));
        }

        return redirect()->route('admin.events.attendees.index', $event)
            ->with('success', "{$attendee->name} updated.");
    }

    public function import(ImportAttendeesRequest $request, Event $event): RedirectResponse
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map('trim', fgetcsv($handle));
        $required = ['name', 'organisation', 'email', 'role_title'];

        if (! in_array('name', $header)) {
            fclose($handle);
            return redirect()->route('admin.events.attendees.index', $event)
                ->with('error', 'CSV must include at least a "name" column.');
        }

        $imported = 0;

        DB::transaction(function () use ($handle, $header, $required, $event, &$imported) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($header)) continue;

                $data = array_intersect_key(array_combine($header, $row), array_flip($required));
                if (empty($data['name'])) continue;

                $attendee = Attendee::create($data);
                $this->passService->issuePass($event, $attendee, null);
                $imported++;
            }
        });

        fclose($handle);

        return redirect()->route('admin.events.attendees.index', $event)
            ->with('success', "Imported {$imported} attendees and issued passes for all days.");
    }

    public function export(Request $request, Event $event): StreamedResponse
    {
        $this->authorize('manage-events', $request->user());

        $filename = str($event->name)->slug() . '-attendees.csv';

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Organisation', 'Email', 'Role', 'Pass Status', 'Days Accredited', 'Last Check-in']);

            EventPass::with(['attendee', 'days', 'entryLogs' => fn($q) => $q->latest('scanned_at')->limit(1)])
                ->where('event_id', $event->id)
                ->chunk(200, function ($passes) use ($out) {
                    foreach ($passes as $pass) {
                        $lastEntry = $pass->entryLogs->first();
                        fputcsv($out, [
                            $pass->attendee->name,
                            $pass->attendee->organisation,
                            $pass->attendee->email,
                            $pass->attendee->role_title,
                            $pass->status,
                            $pass->days->pluck('day_number')->implode(','),
                            $lastEntry?->scanned_at?->toDateTimeString() ?? 'Not checked in',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function revokePass(Request $request, EventPass $pass): RedirectResponse
    {
        $this->authorize('manage-events', $request->user());

        $this->passService->revoke($pass);

        return redirect()->back()->with('success', 'Pass revoked.');
    }
}
