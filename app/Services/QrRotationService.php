<?php

namespace App\Services;

use App\Models\AttendanceQrCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QrRotationService
{
    private const CACHE_KEY = 'attendance.current_qr';

    /**
     * Generate a fresh code. Called by the scheduler on a timer.
     * generated_by is left null to mark this as a system rotation,
     * not a manual admin refresh.
     */
    public function rotate(): AttendanceQrCode
    {
        $qr = AttendanceQrCode::create([
            'token' => Str::random(48),
            'expires_at' => now()->addSeconds(config('attendance.qr_rotation_seconds')),
            'generated_by' => null,
        ]);

        Cache::forget(self::CACHE_KEY);

        return $qr;
    }

    /**
     * The currently live code. Rotates automatically on read if the
     * cached code has lapsed and the scheduler hasn't caught up yet,
     * so the kiosk never shows a dead code.
     */
    public function current(): AttendanceQrCode
    {
        return Cache::remember(self::CACHE_KEY, 5, function () {
            return AttendanceQrCode::active()->latest()->first()
                ?? $this->rotate();
        });
    }

    /**
     * Admin-triggered immediate refresh — e.g. if a code was
     * photographed and they want to invalidate it right away.
     */
    public function forceRegenerate(User $admin): AttendanceQrCode
    {
        $qr = AttendanceQrCode::create([
            'token' => Str::random(48),
            'expires_at' => now()->addSeconds(config('attendance.qr_rotation_seconds')),
            'generated_by' => $admin->id,
        ]);

        Cache::forget(self::CACHE_KEY);

        return $qr;
    }
}
