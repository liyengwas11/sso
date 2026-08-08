<?php

namespace App\Notifications;

use App\Models\EventEntryLog;
use Illuminate\Notifications\Notification;

class DuplicateScanNotification extends Notification
{
    public function __construct(private readonly EventEntryLog $entryLog)
    {
    }

    public function via($notifiable): array
    {
        // Database only for Phase 1 — shows up in the admin bell/Flags
        // page immediately. Add 'mail' here later if email alerts
        // during the event turn out to be wanted.
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $pass = $this->entryLog->eventPass;
        $attendee = $pass->attendee;

        return [
            'event_entry_log_id' => $this->entryLog->id,
            'event_id' => $pass->event_id,
            'event_name' => $pass->event->name,
            'attendee_name' => $attendee->name,
            'attendee_organisation' => $attendee->organisation,
            'reason' => $this->entryLog->reason,
            'scanned_at' => $this->entryLog->scanned_at->toIso8601String(),
            'message' => "Repeat scan: {$attendee->name} ({$attendee->organisation}) at \"{$pass->event->name}\".",
        ];
    }
}
