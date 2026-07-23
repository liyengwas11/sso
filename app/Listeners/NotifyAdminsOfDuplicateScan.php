<?php

namespace App\Listeners;

use App\Events\DuplicateScanDetected;
use App\Models\User;
use App\Notifications\DuplicateScanNotification;

class NotifyAdminsOfDuplicateScan
{
    public function handle(DuplicateScanDetected $event): void
    {
        // Anyone who can manage events gets notified, not just the
        // scanning staff member's own supervisor — deliberately
        // broad for a small event-ops team.
        $admins = User::permission('manage-events')->get();

        foreach ($admins as $admin) {
            $admin->notify(new DuplicateScanNotification($event->entryLog));
        }
    }
}
