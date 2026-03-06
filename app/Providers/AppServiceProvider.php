<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Observers\AppointmentObserver;
use App\Services\Tenant\TenantContextService;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class);
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Appointment::observe(AppointmentObserver::class);
    }
}
