<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Repositories\OnboardingRepository;
use App\Contracts\Services\BillingService as BillingServiceContract;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Contracts\Services\VatService as VatServiceContract;
use App\Models\Appointment;
use App\Observers\AppointmentObserver;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Repositories\Eloquent\EloquentOnboardingRepository;
use App\Services\Billing\BillingService;
use App\Services\Billing\VatService;
use App\Services\Stripe\StripeService;
use App\Services\Tenant\TenantContextService;
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

        // Onboarding bindings
        $this->app->bind(OnboardingRepository::class, EloquentOnboardingRepository::class);
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
    }
}
