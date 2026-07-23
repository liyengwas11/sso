<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use Illuminate\Support\Str;

class PassService
{
   
    public function issuePass(Event $event, Attendee $attendee, ?array $dayNumbers = null): EventPass
    {
        $pass = EventPass::firstOrCreate(
            ['event_id' => $event->id, 'attendee_id' => $attendee->id],
            ['token' => Str::random(48), 'status' => 'active', 'issued_at' => now()]
        );

        $days = $dayNumbers === null
            ? $event->days // all days
            : $event->days()->whereIn('day_number', $dayNumbers)->get();

        $pass->days()->sync($days->pluck('id'));

        return $pass;
    }

    public function revoke(EventPass $pass): void
    {
        $pass->update(['status' => 'revoked']);
    }

    public function reissue(EventPass $pass): EventPass
    {
        $pass->update([
            'token' => Str::random(48),
            'status' => 'active',
            'issued_at' => now(),
        ]);

        return $pass;
    }
}
