<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\FeatureGateServiceContract;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Contracts\Services\UsageValidationServiceContract;
use App\Repositories\Eloquent\EloquentPlanRepository;
use App\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Repositories\Eloquent\EloquentUsageRecordRepository;
use App\Services\Subscription\FeatureGateService;
use App\Services\Subscription\Strategies\FreeSubscriptionStrategy;
use App\Services\Subscription\Strategies\PaidSubscriptionStrategy;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionStrategyResolver;
use App\Services\Subscription\UsageLimitService;
use App\Services\Subscription\UsageValidationService;
use Illuminate\Support\ServiceProvider;

final class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
        $this->registerStrategies();
    }

    private function registerRepositories(): void
    {
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(PlanRepository::class, EloquentPlanRepository::class);
        $this->app->bind(UsageRecordRepository::class, EloquentUsageRecordRepository::class);
    }

    private function registerServices(): void
    {
        $this->app->bind(SubscriptionServiceContract::class, SubscriptionService::class);
        $this->app->bind(UsageLimitServiceContract::class, UsageLimitService::class);
        $this->app->bind(UsageValidationServiceContract::class, UsageValidationService::class);
        $this->app->bind(FeatureGateServiceContract::class, FeatureGateService::class);
    }

    private function registerStrategies(): void
    {
        $this->app->singleton(SubscriptionStrategyResolver::class, static function ($app): SubscriptionStrategyResolver {
            return new SubscriptionStrategyResolver([
                $app->make(FreeSubscriptionStrategy::class),
                $app->make(PaidSubscriptionStrategy::class),
            ]);
        });
    }
}
