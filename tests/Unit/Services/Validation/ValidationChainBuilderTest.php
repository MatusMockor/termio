<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageValidationServiceContract;
use App\Contracts\Validation\SubscriptionValidator;
use App\Services\Validation\ValidationChainBuilder;
use App\Services\Validation\Validators\CanDowngradeValidator;
use App\Services\Validation\Validators\CanUpgradeValidator;
use App\Services\Validation\Validators\PlanExistsValidator;
use App\Services\Validation\Validators\SubscriptionExistsValidator;
use App\Services\Validation\Validators\UsageLimitsValidator;
use Mockery;
use Tests\TestCase;

final class ValidationChainBuilderTest extends TestCase
{
    private ValidationChainBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $subscriptionService = Mockery::mock(SubscriptionServiceContract::class);
        $usageValidation = Mockery::mock(UsageValidationServiceContract::class);

        $this->builder = new ValidationChainBuilder(
            subscriptionExistsValidator: new SubscriptionExistsValidator,
            planExistsValidator: new PlanExistsValidator,
            canDowngradeValidator: new CanDowngradeValidator($subscriptionService),
            canUpgradeValidator: new CanUpgradeValidator($subscriptionService),
            usageLimitsValidator: new UsageLimitsValidator($usageValidation),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_downgrade_chain_returns_validator(): void
    {
        $chain = $this->builder->buildDowngradeChain();

        $this->assertInstanceOf(SubscriptionValidator::class, $chain);
    }

    public function test_build_upgrade_chain_returns_validator(): void
    {
        $chain = $this->builder->buildUpgradeChain();

        $this->assertInstanceOf(SubscriptionValidator::class, $chain);
    }

    public function test_downgrade_chain_starts_with_subscription_exists_validator(): void
    {
        $chain = $this->builder->buildDowngradeChain();

        $this->assertInstanceOf(SubscriptionExistsValidator::class, $chain);
    }

    public function test_upgrade_chain_starts_with_subscription_exists_validator(): void
    {
        $chain = $this->builder->buildUpgradeChain();

        $this->assertInstanceOf(SubscriptionExistsValidator::class, $chain);
    }
}
