<?php

namespace App\Services;

use App\Events\DuplicateScanDetected;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventEntryLog;
use App\Models\EventPass;
use App\Models\User;
use Illuminate\Http\Request;

class CheckInService
{
    /**
     * Central attendance decision point for both check-in and check-out.
     * Always returns the attendee's identity when the pass is found,
     * so staff have what they need to make a human judgment call.
     *
     * @return array{
     *     status: string,
     *     pass: ?EventPass,
     *     message: string,
     *     original_check_in?: array,
     *     current_status?: string
     * }
     */
    public function processScan(
        Event $event,
        string $token,
        User $staff,
        Request $request,
        bool $override = false,
        string $type = 'check-in',
        EventDay $eventDay = null
    ): array {
        // Validate scan type
        if (!in_array($type, ['check-in', 'check-out'])) {
            return [
                'status' => 'invalid',
                'pass' => null,
                'message' => 'Invalid scan type.'
            ];
        }

        // Resolve event day (use provided or find today)
        $today = $eventDay ?? $event->days()->whereDate('date', now()->toDateString())->first();

        if (!$today) {
            return [
                'status' => 'not_event_day',
                'pass' => null,
                'message' => "\"{$event->name}\" isn't running today."
            ];
        }

        // Find the pass
        $pass = EventPass::with(['attendee', 'attendee.media', 'days'])
            ->where('token', $token)
            ->first();

        if (!$pass) {
            return [
                'status' => 'invalid',
                'pass' => null,
                'message' => 'Pass not recognised.'
            ];
        }

        if ($pass->event_id !== $event->id) {
            return [
                'status' => 'wrong_event',
                'pass' => $pass,
                'message' => 'This pass is for a different event.'
            ];
        }

        if ($pass->isRevoked()) {
            return [
                'status' => 'revoked',
                'pass' => $pass,
                'message' => 'This pass has been revoked.'
            ];
        }

        // Check day accreditation for BOTH check-in and check-out
        if (!$pass->isAccreditedFor($today)) {
            $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'denied',
                reason: 'not_accredited_today',
                flagged: false,
                type: $type
            );
            return [
                'status' => 'not_accredited_today',
                'pass' => $pass,
                'message' => "This pass isn't accredited for Day {$today->day_number}."
            ];
        }

        // Get current status for this day
        $currentStatus = $this->getCurrentStatusForDay($pass, $today);

        // Route to appropriate handler based on type
        if ($type === 'check-in') {
            return $this->handleCheckIn($pass, $today, $staff, $request, $override, $currentStatus);
        } else {
            return $this->handleCheckOut($pass, $today, $staff, $request, $override, $currentStatus);
        }
    }

    /**
     * Handle check-in logic with status-based branching
     */
    private function handleCheckIn(
        EventPass $pass,
        EventDay $today,
        User $staff,
        Request $request,
        bool $override,
        string $currentStatus
    ): array {
        $lastEntry = $pass->lastGrantedEntryForDay($today);

        // Scenario 1: Not Checked-In or Checked-Out → Allow check-in
        if (in_array($currentStatus, ['not_checked_in', 'checked_out'])) {
            // If returning from check-out, it's a re-entry
            $message = $currentStatus === 'checked_out'
                ? 'Welcome back! Your re-entry has been recorded.'
                : 'You have successfully checked in.';

            $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'granted',
                reason: null,
                flagged: false,
                type: 'check-in'
            );

            return [
                'status' => 'granted',
                'pass' => $pass,
                'message' => $message,
                'current_status' => 'checked_in'
            ];
        }

        // Scenario 2: Already Checked-In → Duplicate attempt
        if ($currentStatus === 'checked_in') {
            $originalEntry = $pass->lastGrantedEntryForDay($today);

            // Log the duplicate attempt
            $log = $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'duplicate_attempt',
                reason: 'already_checked_in',
                flagged: true,
                type: 'check-in'
            );

            // Notify admins
            DuplicateScanDetected::dispatch($log);

            return [
                'status' => 'already_checked_in',
                'pass' => $pass,
                'message' => "This attendee is already checked in. Duplicate check-in detected.",
                'current_status' => 'checked_in',
                'original_check_in' => [
                    'timestamp' => $originalEntry?->scanned_at?->toDateTimeString(),
                    'staff' => $originalEntry?->scannedBy?->name,
                    'gate' => $originalEntry?->gate_location ?? 'Not recorded',
                ]
            ];
        }

        // Fallback (should never happen)
        return [
            'status' => 'error',
            'pass' => $pass,
            'message' => 'Unable to process check-in. Please try again.'
        ];
    }

    /**
     * Handle check-out logic with status-based branching
     */
    private function handleCheckOut(
        EventPass $pass,
        EventDay $today,
        User $staff,
        Request $request,
        bool $override,
        string $currentStatus
    ): array {
        // Scenario 1: Checked-In → Allow check-out
        if ($currentStatus === 'checked_in') {
            $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'granted',
                reason: null,
                flagged: false,
                type: 'check-out'
            );

            return [
                'status' => 'granted',
                'pass' => $pass,
                'message' => 'Your attendance has been updated successfully.',
                'current_status' => 'checked_out'
            ];
        }

        // Scenario 2: Not Checked-In → Can't check out
        if ($currentStatus === 'not_checked_in') {
            $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'denied',
                reason: 'not_checked_in',
                flagged: false,
                type: 'check-out'
            );

            return [
                'status' => 'not_checked_in',
                'pass' => $pass,
                'message' => 'This attendee has not checked in and therefore cannot be checked out.',
                'current_status' => 'not_checked_in'
            ];
        }

        // Scenario 3: Already Checked-Out → Duplicate attempt
        if ($currentStatus === 'checked_out') {
            $this->log(
                pass: $pass,
                eventDayId: $today->id,
                staff: $staff,
                request: $request,
                result: 'duplicate_attempt',
                reason: 'already_checked_out',
                flagged: false, // Not flagged for admin notification
                type: 'check-out'
            );

            return [
                'status' => 'already_checked_out',
                'pass' => $pass,
                'message' => 'This attendee has already been checked out.',
                'current_status' => 'checked_out'
            ];
        }

        // Fallback
        return [
            'status' => 'error',
            'pass' => $pass,
            'message' => 'Unable to process check-out. Please try again.'
        ];
    }

    /**
     * Get the current attendance status for a pass on a specific day
     */
    private function getCurrentStatusForDay(EventPass $pass, EventDay $day): string
    {
        $latestGranted = $pass->entryLogs()
            ->where('event_day_id', $day->id)
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();

        if (!$latestGranted) {
            return 'not_checked_in';
        }

        return $latestGranted->type; // 'check-in' or 'check-out'
    }

    /**
     * Unified logging method for all scan attempts
     */
    private function log(
        EventPass $pass,
        int $eventDayId,
        User $staff,
        Request $request,
        string $result,
        ?string $reason,
        bool $flagged,
        string $type
    ): EventEntryLog {
        return EventEntryLog::create([
            'event_pass_id' => $pass->id,
            'event_day_id' => $eventDayId,
            'scanned_by' => $staff->id,
            'scanned_at' => now(),
            'result' => $result,
            'reason' => $reason,
            'device_type' => $this->detectDeviceType($request->userAgent()),
            'flagged' => $flagged,
            'type' => $type,
            'gate_location' => $request->input('gate_location'),
        ]);
    }

    private function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';

        return match (true) {
            (bool) preg_match('/tablet|ipad/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
