<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AppointmentRepository;
use App\Contracts\Repositories\ClientRepository;
use App\Contracts\Repositories\ServiceRepository;
use App\Contracts\Repositories\StaffRepository;
use App\Contracts\Repositories\TenantRepository;
use App\Contracts\Repositories\TimeOffRepository;
use App\Contracts\Repositories\UserRepository;
use App\Contracts\Repositories\WorkingHoursRepository;
use App\Models\Appointment;
use App\Observers\AppointmentObserver;
use App\Repositories\Eloquent\EloquentAppointmentRepository;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentServiceRepository;
use App\Repositories\Eloquent\EloquentStaffRepository;
use App\Repositories\Eloquent\EloquentTenantRepository;
use App\Repositories\Eloquent\EloquentTimeOffRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\EloquentWorkingHoursRepository;
use App\Services\Tenant\TenantContextService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class);

        // Repository bindings
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(TenantRepository::class, EloquentTenantRepository::class);
        $this->app->bind(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->bind(ServiceRepository::class, EloquentServiceRepository::class);
        $this->app->bind(ClientRepository::class, EloquentClientRepository::class);
        $this->app->bind(TimeOffRepository::class, EloquentTimeOffRepository::class);
        $this->app->bind(WorkingHoursRepository::class, EloquentWorkingHoursRepository::class);
        $this->app->bind(StaffRepository::class, EloquentStaffRepository::class);
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
    }
}
