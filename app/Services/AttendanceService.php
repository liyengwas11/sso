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
