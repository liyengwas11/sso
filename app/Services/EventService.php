<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\User;
use Illuminate\Support\Carbon;

class EventService
{
    /**
     * Regenerates the EventDay rows to match start_date/end_date.
     * Call this after creating or updating an event. Idempotent and
     * safe to call even when dates haven't changed.
     */
    public function syncEventDays(Event $event): void
    {
        $start = Carbon::parse($event->start_date);
        $end = Carbon::parse($event->end_date);

        $wantedDates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $wantedDates->push($date->toDateString());
        }

        // Drop days outside the new range (e.g. event shortened),
        // keep/renumber the rest.
        $event->days()->whereNotIn('date', $wantedDates)->delete();

        foreach ($wantedDates as $i => $date) {
            EventDay::updateOrCreate(
                ['event_id' => $event->id, 'date' => $date],
                ['day_number' => $i + 1]
            );
        }
    }

    /**
     * Clones a recurring event ~1 year forward, including its day
     * structure. Attendees are re-invited but EVERY pass is freshly
     * issued with a new token and re-accredited for the new event's
     * days — last year's badge must not work at this year's event.
     */
    public function cloneForNextYear(Event $event, User $admin, bool $copyAttendees = true): Event
    {
        $clone = Event::create([
            'name' => $event->name,
            'description' => $event->description,
            'venue' => $event->venue,
            'start_date' => Carbon::parse($event->start_date)->addYear(),
            'end_date' => Carbon::parse($event->end_date)->addYear(),
            'is_recurring' => true,
            'parent_event_id' => $event->id,
            'created_by' => $admin->id,
        ]);

        $this->syncEventDays($clone);

        if ($copyAttendees) {
            $passService = app(PassService::class);
            foreach ($event->passes()->with('attendee')->get() as $pass) {
                // Re-accredit for the SAME day-numbers (not the same
                // calendar dates, which no longer exist) — e.g.
                // someone accredited for Day 1+2 last year gets Day
                // 1+2 of the new event, whatever dates those fall on.
                $dayNumbers = $pass->days->pluck('day_number');
                $passService->issuePass($clone, $pass->attendee, $dayNumbers->isNotEmpty() ? $dayNumbers->toArray() : null);
            }
        }

        return $clone;
    }
}
