<?php

namespace App\Services;

use App\Exceptions\DuplicateScanException;
use App\Exceptions\ExpiredQrException;
use App\Models\AttendanceLog;
use App\Models\AttendanceQrCode;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AttendanceService
{
    /**
     * Validate the scanned token and record a clock-in or clock-out,
     * auto-toggling based on the user's last entry for the day.
     *
     * @throws ExpiredQrException
     * @throws DuplicateScanException
     */
    public function processScan(User $user, string $token, Request $request): AttendanceLog
    {
        $qr = AttendanceQrCode::where('token', $token)
            ->active()
            ->first();

        if (! $qr) {
            throw new ExpiredQrException();
        }

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

    public function historyForUser(User $user, ?string $from = null, ?string $to = null, int $perPage = 20): LengthAwarePaginator
    {
        return AttendanceLog::forUser($user->id)
            ->when($from, fn ($q) => $q->whereDate('scanned_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('scanned_at', '<=', $to))
            ->latest('scanned_at')
            ->paginate($perPage);
    }

    private function hasRecentDuplicate(User $user): bool
    {
        return AttendanceLog::forUser($user->id)
            ->where('scanned_at', '>', now()->subSeconds(config('attendance.duplicate_cooldown_seconds')))
            ->exists();
    }

    /**
     * Determine in/out based on the user's most recent log *for today*
     * in their own timezone — so a clock-in just before midnight
     * doesn't force a clock-out the moment the calendar day rolls over.
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

        // Lightweight heuristic for Phase 1. Swap in jenssegers/agent
        // (or similar) if richer device breakdown is needed later.
        return match (true) {
            (bool) preg_match('/tablet|ipad/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
