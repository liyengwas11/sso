<?php

namespace App\Providers;

use App\Events\DuplicateScanDetected;
use App\Listeners\NotifyAdminsOfDuplicateScan;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DuplicateScanDetected::class => [
            NotifyAdminsOfDuplicateScan::class,
        ],
    ];
}
