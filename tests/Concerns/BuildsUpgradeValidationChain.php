<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageValidationServiceContract;
use App\Services\Validation\ValidationChainBuilder;
use App\Services\Validation\Validators\CanDowngradeValidator;
use App\Services\Validation\Validators\CanUpgradeValidator;
use App\Services\Validation\Validators\PlanExistsValidator;
use App\Services\Validation\Validators\SubscriptionExistsValidator;
use App\Services\Validation\Validators\UsageLimitsValidator;

trait BuildsUpgradeValidationChain
{
    protected function createUpgradeValidationChainBuilder(): ValidationChainBuilder
    {
        $subscriptionService = $this->createMock(SubscriptionServiceContract::class);
        $subscriptionService->method('canUpgradeTo')->willReturn(true);
        $subscriptionService->method('canDowngradeTo')->willReturn(true);

        $usageValidationService = $this->createMock(UsageValidationServiceContract::class);
        $usageValidationService->method('checkLimitViolations')->willReturn([]);

        return new ValidationChainBuilder(
            new SubscriptionExistsValidator,
            new PlanExistsValidator,
            new CanDowngradeValidator($subscriptionService),
            new CanUpgradeValidator($subscriptionService),
            new UsageLimitsValidator($usageValidationService),
        );
    }
}
