<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrRotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function __construct(private readonly QrRotationService $qrRotationService)
    {
    }

    /**
     * GET /api/admin/attendance/qr-code
     * Returns the currently live code plus a rendered SVG, for the
     * kiosk display page to render (Phase 2).
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorize('manage-qr-code', $request->user());

        $qr = $this->qrRotationService->current();

        return response()->json([
            'token' => $qr->token,
            'expires_at' => $qr->expires_at->toIso8601String(),
            'seconds_remaining' => max(0, now()->diffInSeconds($qr->expires_at, false)),
            'svg' => QrCode::size(320)->generate($qr->token),
        ]);
    }

    /**
     * POST /api/admin/attendance/qr-code/refresh
     * Manual, immediate invalidation — e.g. the displayed code was
     * photographed and an admin wants to kill it right away.
     */
    public function refresh(Request $request): JsonResponse
    {
        $this->authorize('manage-qr-code', $request->user());

        $qr = $this->qrRotationService->forceRegenerate($request->user());

        return response()->json([
            'token' => $qr->token,
            'expires_at' => $qr->expires_at->toIso8601String(),
            'svg' => QrCode::size(320)->generate($qr->token),
        ]);
    }
}
