<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardSubscriptionController;
use App\Http\Controllers\Api\GoogleCalendarController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PortfolioImageController;
use App\Http\Controllers\Api\PortfolioTagController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionFeatureController;
use App\Http\Controllers\Api\TimeOffController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\PortfolioController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Plan Routes (no authentication required)
|--------------------------------------------------------------------------
*/
Route::prefix('plans')->name('plans.')->group(static function (): void {
    Route::get('/', [PlanController::class, 'index'])->name('index');
    Route::get('/compare', [PlanController::class, 'compare'])->name('compare');
    Route::get('/{plan:slug}', [PlanController::class, 'show'])->name('show');
});

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
    // Onboarding
    Route::prefix('onboarding')->name('onboarding.')->group(function (): void {
        Route::get('/status', [OnboardingController::class, 'status'])->name('status');
        Route::get('/templates/{businessType}', [OnboardingController::class, 'templates'])->name('templates');
        Route::post('/start', [OnboardingController::class, 'start'])->name('start');
        Route::post('/save-progress', [OnboardingController::class, 'saveProgress'])->name('save-progress');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
        Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/report', [DashboardController::class, 'report'])->name('dashboard.report');
    Route::middleware('owner')->get('/dashboard/subscription', [DashboardSubscriptionController::class, 'show'])->name('dashboard.subscription');

    // Appointments - check reservation limit on creation
    Route::get('/appointments/calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
    Route::get('/appointments/calendar/day', [AppointmentController::class, 'calendarDay'])->name('appointments.calendar.day');
    Route::apiResource('appointments', AppointmentController::class)->except(['store']);
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('check.reservation.limit')
        ->name('appointments.store');
    Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    // Services - check service limit on creation
    Route::apiResource('services', ServiceController::class)->except(['store']);
    Route::post('/services', [ServiceController::class, 'store'])
        ->middleware('check.service.limit')
        ->name('services.store');
    Route::post('/services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');

    // Clients
    Route::apiResource('clients', ClientController::class);
    Route::get('/clients-search', [ClientController::class, 'search'])->name('clients.search');

    // Staff - check user limit on creation
    Route::apiResource('staff', StaffController::class)->except(['store']);
    Route::post('/staff', [StaffController::class, 'store'])
        ->middleware('check.user.limit')
        ->name('staff.store');
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
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
        Route::delete('/settings/logo', [SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    });

    // Google Calendar Integration (requires google_calendar_sync feature)
    Route::prefix('integrations/google-calendar')
        ->middleware('feature:google_calendar_sync')
        ->name('google-calendar.')
        ->group(function (): void {
            Route::get('/status', [GoogleCalendarController::class, 'status'])->name('status');
            Route::get('/connect', [GoogleCalendarController::class, 'connect'])->name('connect');
            Route::post('/callback', [GoogleCalendarController::class, 'callback'])->name('callback');
            Route::delete('/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('disconnect');
        });

    // Subscriptions (owner only)
    Route::middleware('owner')->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
        Route::post('/', [SubscriptionController::class, 'store'])->name('store');
        Route::get('/', [SubscriptionController::class, 'show'])->name('show');
        Route::get('/usage', [SubscriptionController::class, 'usage'])->name('usage');
        Route::post('/upgrade', [SubscriptionController::class, 'upgrade'])->name('upgrade');
        Route::post('/upgrade/immediate', [SubscriptionController::class, 'upgradeImmediate'])->name('upgrade-immediate');
        Route::post('/downgrade', [SubscriptionController::class, 'downgrade'])->name('downgrade');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('resume');

        // Feature status endpoints
        Route::get('/features', [SubscriptionFeatureController::class, 'index'])->name('features.index');
        Route::get('/features/grouped', [SubscriptionFeatureController::class, 'grouped'])->name('features.grouped');
        Route::get('/features/{feature}', [SubscriptionFeatureController::class, 'show'])->name('features.show');
    });

    // Billing (owner only)
    Route::middleware('owner')->prefix('billing')->name('billing.')->group(function (): void {
        // Invoices
        Route::get('/invoices', [BillingController::class, 'invoices'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [BillingController::class, 'downloadInvoice'])->name('invoices.download');

        Route::get('/payment-methods', [BillingController::class, 'paymentMethods'])->name('payment-methods.index');
        Route::post('/portal-session', [BillingController::class, 'createPortalSession'])->name('portal-session');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes (require authentication + admin role)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->name('admin.')
    ->group(static function (): void {
        Route::get('/plans/statistics', [AdminPlanController::class, 'statistics'])->name('plans.statistics');
        Route::apiResource('plans', AdminPlanController::class);
    });

/*
|--------------------------------------------------------------------------
| Stripe Webhooks (no authentication, signature verified by Cashier)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

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
    Route::get('/available-dates', [BookingController::class, 'availableDates'])->name('available-dates');
    Route::post('/create', [BookingController::class, 'create'])->name('create');

    // Public Portfolio Gallery
    Route::get('/gallery', [PortfolioController::class, 'gallery'])->name('gallery');
    Route::get('/gallery/tags', [PortfolioController::class, 'tags'])->name('gallery.tags');
    Route::get('/gallery/{staffId}', [PortfolioController::class, 'staffGallery'])->name('gallery.staff');
});
