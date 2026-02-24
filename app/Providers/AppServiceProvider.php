<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Services\BillingService as BillingServiceContract;
use App\Contracts\Services\BookingAvailability;
use App\Contracts\Services\PaymentMethodServiceContract;
use App\Contracts\Services\PublicBookingRead;
use App\Contracts\Services\ReportingDataProvider;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Contracts\Services\VatService as VatServiceContract;
use App\Contracts\Services\WorkingHoursBusiness;
use App\Models\Appointment;
use App\Observers\AppointmentObserver;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentMethodService;
use App\Services\Billing\VatService;
use App\Services\Booking\BookingAvailabilityService;
use App\Services\Booking\PublicBookingReadService;
use App\Services\Reporting\ReportingDataProviderService;
use App\Services\Stripe\StripeService;
use App\Services\Tenant\TenantContextService;
use App\Services\WorkingHours\WorkingHoursBusinessService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class);
        $this->app->bind(StripeServiceContract::class, StripeService::class);

        // Billing bindings
        $this->app->bind(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->bind(VatServiceContract::class, VatService::class);
        $this->app->bind(BillingServiceContract::class, BillingService::class);
        $this->app->bind(PaymentMethodServiceContract::class, PaymentMethodService::class);
        $this->app->bind(BookingAvailability::class, BookingAvailabilityService::class);
        $this->app->bind(PublicBookingRead::class, PublicBookingReadService::class);
        $this->app->bind(ReportingDataProvider::class, ReportingDataProviderService::class);
        $this->app->bind(WorkingHoursBusiness::class, WorkingHoursBusinessService::class);
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
    }
}
