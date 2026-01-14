<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GoogleCalendarController;
use App\Http\Controllers\Api\PortfolioImageController;
use App\Http\Controllers\Api\PortfolioTagController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TimeOffController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\PortfolioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/google/redirect', [AuthController::class, 'googleRedirect'])->name('google.redirect');
    Route::get('/google/callback', [AuthController::class, 'googleCallback'])->name('google.callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'currentUser'])->name('me');
    });
});

/*
|--------------------------------------------------------------------------
| Protected Business Routes (require authentication + tenant)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/report', [DashboardController::class, 'report'])->name('dashboard.report');

    // Appointments
    Route::apiResource('appointments', AppointmentController::class);
    Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    // Services
    Route::apiResource('services', ServiceController::class);
    Route::post('/services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');

    // Clients
    Route::apiResource('clients', ClientController::class);
    Route::get('/clients-search', [ClientController::class, 'search'])->name('clients.search');

    // Staff
    Route::apiResource('staff', StaffController::class);
    Route::post('/staff/reorder', [StaffController::class, 'reorder'])->name('staff.reorder');
    Route::get('/staff/{staff}/working-hours', [StaffController::class, 'getWorkingHours'])->name('staff.working-hours.index');
    Route::put('/staff/{staff}/working-hours', [StaffController::class, 'updateWorkingHours'])->name('staff.working-hours.update');

    // Time Off
    Route::apiResource('time-off', TimeOffController::class)->parameters(['time-off' => 'timeOff']);

    // Portfolio Images
    Route::apiResource('portfolio-images', PortfolioImageController::class);
    Route::post('/portfolio-images/reorder', [PortfolioImageController::class, 'reorder'])->name('portfolio-images.reorder');
    Route::get('/staff/{staff}/portfolio', [PortfolioImageController::class, 'byStaff'])->name('staff.portfolio');

    // Portfolio Tags
    Route::apiResource('portfolio-tags', PortfolioTagController::class)->except(['show']);

    // Settings (owner only)
    Route::middleware('owner')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/working-hours', [SettingsController::class, 'updateWorkingHours'])->name('settings.working-hours');
    });

    // Google Calendar Integration
    Route::prefix('integrations/google-calendar')->name('google-calendar.')->group(function (): void {
        Route::get('/status', [GoogleCalendarController::class, 'status'])->name('status');
        Route::get('/connect', [GoogleCalendarController::class, 'connect'])->name('connect');
        Route::post('/callback', [GoogleCalendarController::class, 'callback'])->name('callback');
        Route::delete('/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('disconnect');
    });
});

/*
|--------------------------------------------------------------------------
| Public Booking Routes (no authentication required)
|--------------------------------------------------------------------------
*/
Route::prefix('book/{tenantSlug}')->name('booking.')->group(function (): void {
    Route::get('/info', [BookingController::class, 'tenantInfo'])->name('info');
    Route::get('/services', [BookingController::class, 'services'])->name('services');
    Route::get('/staff', [BookingController::class, 'staff'])->name('staff');
    Route::get('/staff/{staffId}/services', [BookingController::class, 'staffServices'])->name('staff.services');
    Route::get('/availability', [BookingController::class, 'availability'])->name('availability');
    Route::post('/create', [BookingController::class, 'create'])->name('create');

    // Public Portfolio Gallery
    Route::get('/gallery', [PortfolioController::class, 'gallery'])->name('gallery');
    Route::get('/gallery/tags', [PortfolioController::class, 'tags'])->name('gallery.tags');
    Route::get('/gallery/{staffId}', [PortfolioController::class, 'staffGallery'])->name('gallery.staff');
});
