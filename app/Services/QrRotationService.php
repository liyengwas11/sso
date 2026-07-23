<?php

namespace App\Services;

use App\Models\AttendanceQrCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QrRotationService
{
    private const CACHE_KEY = 'attendance.current_qr';

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

    public function current(): AttendanceQrCode
    {
        return Cache::remember(self::CACHE_KEY, 5, function () {
            return AttendanceQrCode::active()->latest()->first()
                ?? $this->rotate();
        });
    }

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
