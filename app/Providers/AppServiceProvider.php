<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\InvoiceRepository;
use App\Contracts\Repositories\OnboardingRepository;
use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\BillingService as BillingServiceContract;
use App\Contracts\Services\FeatureGateServiceContract;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Contracts\Services\UsageValidationServiceContract;
use App\Contracts\Services\VatService as VatServiceContract;
use App\Models\Appointment;
use App\Observers\AppointmentObserver;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Repositories\Eloquent\EloquentOnboardingRepository;
use App\Repositories\Eloquent\EloquentPlanRepository;
use App\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Repositories\Eloquent\EloquentUsageRecordRepository;
use App\Services\Billing\BillingService;
use App\Services\Billing\VatService;
use App\Services\Stripe\StripeService;
use App\Services\Subscription\FeatureGateService;
use App\Services\Subscription\Strategies\FreeSubscriptionStrategy;
use App\Services\Subscription\Strategies\PaidSubscriptionStrategy;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionStrategyResolver;
use App\Services\Subscription\UsageLimitService;
use App\Services\Subscription\UsageValidationService;
use App\Services\Tenant\TenantContextService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class);
        $this->app->bind(StripeServiceContract::class, StripeService::class);

        // Subscription bindings
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(PlanRepository::class, EloquentPlanRepository::class);
        $this->app->bind(SubscriptionServiceContract::class, SubscriptionService::class);

        // Usage limit bindings
        $this->app->bind(UsageRecordRepository::class, EloquentUsageRecordRepository::class);
        $this->app->bind(UsageLimitServiceContract::class, UsageLimitService::class);
        $this->app->bind(UsageValidationServiceContract::class, UsageValidationService::class);

        // Feature gate binding
        $this->app->bind(FeatureGateServiceContract::class, FeatureGateService::class);

        // Billing bindings
        $this->app->bind(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->bind(VatServiceContract::class, VatService::class);
        $this->app->bind(BillingServiceContract::class, BillingService::class);

        // Onboarding bindings
        $this->app->bind(OnboardingRepository::class, EloquentOnboardingRepository::class);

        // Subscription strategy resolver
        $this->app->singleton(SubscriptionStrategyResolver::class, static function ($app): SubscriptionStrategyResolver {
            return new SubscriptionStrategyResolver([
                $app->make(FreeSubscriptionStrategy::class),
                $app->make(PaidSubscriptionStrategy::class),
            ]);
        });
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
    }
}
