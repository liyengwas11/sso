<?php
// config/event.php

return [
    /*
    |--------------------------------------------------------------------------
    | Gate/Location Options
    |--------------------------------------------------------------------------
    |
    | Available gates/locations that staff can select during check-in/out.
    | These can be overridden per event or customized via the admin UI.
    |
    */
    'gates' => [
        'Main Entrance',
        'VIP Entrance',
        'Side Gate',
        'Back Entrance',
        'Staff Entrance',
        'Exhibition Hall',
        'Conference Room A',
        'Conference Room B',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Settings
    |--------------------------------------------------------------------------
    */
    'attendance' => [
        // Allow re-entry after check-out
        'allow_reentry' => true,

        // Allow override for duplicate check-ins
        'allow_override' => true,

        // Minimum time between scans for same pass (in seconds)
        'min_scan_interval' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Notify admins on duplicate check-in attempts
        'notify_on_duplicate' => true,

        // Notify admins on staff overrides
        'notify_on_override' => true,
    ],
];
