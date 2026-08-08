<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage-events', $request->user());

        return Inertia::render('Admin/Events/Index', [
            'events' => Event::withCount('passes')
                ->with('days')
                ->latest('start_date')
                ->paginate(20)
                ->through(fn($event) => [
                    ...$event->toArray(),
                    'cover_url' => $event->coverUrl(),
                ]),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = Event::create([
            ...$request->safe()->except('cover'),
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        }

        $this->eventService->syncEventDays($event);

        return redirect()->route('admin.events.index')
            ->with('success', "\"{$event->name}\" created.");
    }

    public function update(StoreEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->safe()->except('cover'));

        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        }

        $this->eventService->syncEventDays($event);

        return redirect()->route('admin.events.index')
            ->with('success', "\"{$event->name}\" updated.");
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manage-events', $request->user());

        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'Event deleted.');
    }

    public function cloneNextYear(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manage-events', $request->user());

        $clone = $this->eventService->cloneForNextYear($event, $request->user());

        return redirect()->route('admin.events.index')
            ->with('success', "Created \"{$clone->name}\" for {$clone->start_date->format('Y')} with fresh passes for all attendees.");
    }
}
