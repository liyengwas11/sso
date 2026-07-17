<?php

use App\Http\Controllers\Api\Admin\AttendanceLogController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\QrCodeController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

// Public: no login required. An admin displays the QR from their own
// dashboard; the employee scans it on their own phone, self-identifies
// via name + employment number. Protected by the QR token's short
// expiry and throttling rather than a session.
Route::prefix('attendance')->group(function () {
    Route::get('employees', [AttendanceController::class, 'employees'])
        ->middleware('throttle:30,1');
    Route::post('scan', [AttendanceController::class, 'store'])
        ->middleware('throttle:6,1');
});

Route::middleware('auth:sanctum')->group(function () {

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

        Route::apiResource('employees', EmployeeController::class)->except(['create', 'edit']);

        Route::apiResource('roles', RoleController::class)->except(['edit', 'create']);
        Route::apiResource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
        Route::put('users/{user}/roles', [UserRoleController::class, 'update']);
    });
});
