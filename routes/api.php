<?php

use App\Http\Controllers\Api\Admin\AttendanceLogController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\QrCodeController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Employee-facing: any authenticated, active user.
    Route::prefix('attendance')->group(function () {
        Route::post('scan', [AttendanceController::class, 'store'])
            ->middleware('throttle:6,1'); // 6 scans/minute — plenty for real use, blunts abuse
        Route::get('history', [AttendanceController::class, 'history']);
    });

    // Admin-facing. Authorization is enforced inside each controller
    // via $this->authorize(...) against Spatie permissions, rather
    // than a hardcoded 'role:admin' middleware — so a future
    // "Manager" role with a subset of admin permissions works without
    // touching this file.
    Route::prefix('admin')->group(function () {
        Route::get('attendance/qr-code', [QrCodeController::class, 'show']);
        Route::post('attendance/qr-code/refresh', [QrCodeController::class, 'refresh']);

        Route::get('attendance/logs', [AttendanceLogController::class, 'index']);
        Route::get('attendance/logs/live', [AttendanceLogController::class, 'live']);

        Route::apiResource('roles', RoleController::class)->except(['edit', 'create']);
        Route::apiResource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
        Route::put('users/{user}/roles', [UserRoleController::class, 'update']);
    });
});
