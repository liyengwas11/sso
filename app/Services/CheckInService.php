<?php

namespace App\Services;

use App\Events\DuplicateScanDetected;
use App\Models\Event;
use App\Models\EventEntryLog;
use App\Models\EventPass;
use App\Models\User;
use Illuminate\Http\Request;

class CheckInService
{
    /**
     * Central check-in decision point. Always returns the attendee's
     * identity (name/org/photo/role) when the pass is found, even on
     * a denial, so gate staff have what they need to make a human
     * judgment call rather than just seeing "DENIED".
     *
     * @return array{status: string, pass: ?EventPass, message: string}
     */
    public function processScan(Event $event, string $token, User $staff, Request $request, bool $override = false): array
    {
        $pass = EventPass::with(['attendee', 'days'])
            ->where('token', $token)
            ->first();

        if (! $pass) {
            return ['status' => 'invalid', 'pass' => null, 'message' => 'Pass not recognised.'];
        }

        if ($pass->event_id !== $event->id) {
            return ['status' => 'wrong_event', 'pass' => $pass, 'message' => 'This pass is for a different event.'];
        }

        if ($pass->isRevoked()) {
            return ['status' => 'revoked', 'pass' => $pass, 'message' => 'This pass has been revoked.'];
        }

        $today = $event->days()->whereDate('date', now()->toDateString())->first();

        if (! $today) {
            return ['status' => 'not_event_day', 'pass' => $pass, 'message' => "\"{$event->name}\" isn't running today."];
        }

        if (! $pass->isAccreditedFor($today)) {
            $this->log($pass, $today->id, $staff, $request, 'denied', 'not_accredited_today', flagged: false);
            return [
                'status' => 'not_accredited_today',
                'pass' => $pass,
                'message' => "This pass isn't accredited for Day {$today->day_number}.",
            ];
        }

        $lastEntry = $pass->lastGrantedEntryForDay($today);

        if ($lastEntry && ! $override) {
            // Anti-passback: don't auto-deny — log it as flagged
            // (triggers the admin notification + Reports flag) and
            // surface it to staff so they can visually check the
            // photo and decide whether this is a legitimate re-entry.
            $log = $this->log($pass, $today->id, $staff, $request, 'denied', 'already_checked_in', flagged: true);
            DuplicateScanDetected::dispatch($log);

            return [
                'status' => 'already_checked_in',
                'pass' => $pass,
                'message' => "Already checked in today at {$lastEntry->scanned_at->format('g:i A')}.",
            ];
        }

        if ($override) {
            $log = $this->log($pass, $today->id, $staff, $request, 'override_granted', 'staff_override', flagged: true);
            DuplicateScanDetected::dispatch($log);
        } else {
            $this->log($pass, $today->id, $staff, $request, 'granted', null, flagged: false);
        }

        return ['status' => 'granted', 'pass' => $pass, 'message' => 'Entry granted.'];
    }

    private function log(EventPass $pass, int $eventDayId, User $staff, Request $request, string $result, ?string $reason, bool $flagged): EventEntryLog
    {
        return EventEntryLog::create([
            'event_pass_id' => $pass->id,
            'event_day_id' => $eventDayId,
            'scanned_by' => $staff->id,
            'scanned_at' => now(),
            'result' => $result,
            'reason' => $reason,
            'device_type' => $this->detectDeviceType($request->userAgent()),
            'flagged' => $flagged,
        ]);
    }

    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) return 'unknown';

        return match (true) {
            (bool) preg_match('/tablet|ipad/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
