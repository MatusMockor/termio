<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $repositories = [
        \App\Contracts\Repositories\UserRepository::class => \App\Repositories\Eloquent\EloquentUserRepository::class,
        \App\Contracts\Repositories\TenantRepository::class => \App\Repositories\Eloquent\EloquentTenantRepository::class,
        \App\Contracts\Repositories\AppointmentRepository::class => \App\Repositories\Eloquent\EloquentAppointmentRepository::class,
        \App\Contracts\Repositories\ServiceRepository::class => \App\Repositories\Eloquent\EloquentServiceRepository::class,
        \App\Contracts\Repositories\ClientRepository::class => \App\Repositories\Eloquent\EloquentClientRepository::class,
        \App\Contracts\Repositories\TimeOffRepository::class => \App\Repositories\Eloquent\EloquentTimeOffRepository::class,
        \App\Contracts\Repositories\WorkingHoursRepository::class => \App\Repositories\Eloquent\EloquentWorkingHoursRepository::class,
        \App\Contracts\Repositories\StaffRepository::class => \App\Repositories\Eloquent\EloquentStaffRepository::class,
        \App\Contracts\Repositories\PortfolioImageRepository::class => \App\Repositories\Eloquent\EloquentPortfolioImageRepository::class,
        \App\Contracts\Repositories\PortfolioTagRepository::class => \App\Repositories\Eloquent\EloquentPortfolioTagRepository::class,
        \App\Contracts\Repositories\OnboardingRepository::class => \App\Repositories\Eloquent\EloquentOnboardingRepository::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private array $services = [
        \App\Contracts\Services\ImageUploadService::class => \App\Services\Portfolio\ImageUploadService::class,
        \App\Contracts\Services\ReportingMetricsService::class => \App\Services\Reporting\ReportingMetricsService::class,
        \App\Contracts\Services\OnboardingProgressValidationServiceContract::class => \App\Services\Onboarding\OnboardingProgressValidationService::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }

        foreach ($this->services as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
