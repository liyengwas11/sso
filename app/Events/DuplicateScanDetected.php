<?php

namespace App\Events;

use App\Models\EventEntryLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever CheckInService logs a flagged entry — a repeat scan
 * of a pass that was already granted entry (whether staff denied it
 * or overrode it through). The listener below is what actually
 * notifies admins; this class just decouples "something suspicious
 * happened" from "here's how we tell people about it".
 */
class DuplicateScanDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(public EventEntryLog $entryLog)
    {
    }
}
