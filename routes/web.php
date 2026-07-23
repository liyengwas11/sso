<?php

use App\Http\Controllers\Admin\AttendeeController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\FlagController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Events
    Route::resource('events', EventController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('events/{event}/clone-next-year', [EventController::class, 'cloneNextYear'])
        ->name('events.clone-next-year');

    // Attendees + passes, nested under an event
    Route::prefix('events/{event}/attendees')->name('events.attendees.')->group(function () {
        Route::get('/', [AttendeeController::class, 'index'])->name('index');
        Route::post('/', [AttendeeController::class, 'store'])->name('store');
        Route::post('import', [AttendeeController::class, 'import'])->name('import');
        Route::get('export', [AttendeeController::class, 'export'])->name('export');
    });
    Route::post('passes/{pass}/revoke', [AttendeeController::class, 'revokePass'])->name('passes.revoke');

    // Gate scanning
    Route::prefix('events/{event}/scan')->name('events.scan.')->group(function () {
        Route::get('/', [CheckInController::class, 'show'])->name('show');
        Route::post('/', [CheckInController::class, 'store'])->name('store');
    });

    // Per-event report
    Route::get('events/{event}/reports', [ReportController::class, 'show'])->name('events.reports.show');

    // Cross-event flagged-scan review
    Route::get('flags', [FlagController::class, 'index'])->name('flags.index');
    Route::post('flags/{flag}/mark-reviewed', [FlagController::class, 'markReviewed'])->name('flags.mark-reviewed');

    // Staff accounts (people who can log in — admins, gate staff)
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::delete('staff/{staffMember}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Roles & permissions
    Route::resource('roles', RoleController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    Route::patch('users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
});
