<?php

namespace App\Services;

use App\Exceptions\DuplicateScanException;
use App\Exceptions\ExpiredQrException;
use App\Exceptions\InvalidEmployeeException;
use App\Models\AttendanceLog;
use App\Models\AttendanceQrCode;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceService
{
    /**
     * Validate the scanned token, verify the selected employee's
     * identity against their employment number, and record a
     * clock-in or clock-out — auto-toggling based on their last
     * entry for the day.
     *
     * There's no authenticated session here by design: the employee
     * picked their own phone up and scanned a code displayed by an
     * admin, then self-identified. The employment number is what
     * stops someone picking a colleague's name off the list.
     *
     * @throws ExpiredQrException
     * @throws InvalidEmployeeException
     * @throws DuplicateScanException
     */
    public function processScan(string $token, int $userId, string $employmentNumber, Request $request): AttendanceLog
    {
        $qr = AttendanceQrCode::where('token', $token)
            ->active()
            ->first();

        if (! $qr) {
            throw new ExpiredQrException();
        }

        $user = $this->resolveEmployee($userId, $employmentNumber);

        if ($this->hasRecentDuplicate($user)) {
            throw new DuplicateScanException();
        }

        $type = $this->nextLogType($user);

        return AttendanceLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'scanned_at' => now(),
            'qr_code_id' => $qr->id,
            'ip_address' => $request->ip(),
            'device_type' => $this->detectDeviceType($request->userAgent()),
        ]);
    }

    /**
     * Confirms the selected dropdown entry (user_id) actually belongs
     * to the person who typed this employment_number, and that the
     * account is active. Deliberately doesn't distinguish "wrong
     * number" from "unknown user" in the exception message — that
     * would let someone probe which employment numbers exist.
     *
     * @throws InvalidEmployeeException
     */
    private function resolveEmployee(int $userId, string $employmentNumber): User
    {
        $user = User::where('id', $userId)
            ->where('employment_number', $employmentNumber)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            throw new InvalidEmployeeException();
        }

        return $user;
    }

    private function hasRecentDuplicate(User $user): bool
    {
        return AttendanceLog::forUser($user->id)
            ->where('scanned_at', '>', now()->subSeconds(config('attendance.duplicate_cooldown_seconds')))
            ->exists();
    }

    /**
     * Determine in/out based on the employee's most recent log *for
     * today* in their own timezone — so a clock-in just before
     * midnight doesn't force a clock-out the moment the calendar day
     * rolls over.
     */
    private function nextLogType(User $user): string
    {
        $startOfDay = now($user->timezone)->startOfDay()->utc();

        $lastLog = AttendanceLog::forUser($user->id)
            ->where('scanned_at', '>=', $startOfDay)
            ->latest('scanned_at')
            ->first();

        return ($lastLog && $lastLog->type === 'clock_in') ? 'clock_out' : 'clock_in';
    }

    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        return match (true) {
            (bool) preg_match('/tablet|ipad/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
