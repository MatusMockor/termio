<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Contracts\Validation\SubscriptionValidator;
use App\Services\Validation\Validators\CanDowngradeValidator;
use App\Services\Validation\Validators\CanUpgradeValidator;
use App\Services\Validation\Validators\PlanExistsValidator;
use App\Services\Validation\Validators\SubscriptionExistsValidator;
use App\Services\Validation\Validators\UsageLimitsValidator;

final class ValidationChainBuilder
{
    public function __construct(
        private readonly SubscriptionExistsValidator $subscriptionExistsValidator,
        private readonly PlanExistsValidator $planExistsValidator,
        private readonly CanDowngradeValidator $canDowngradeValidator,
        private readonly CanUpgradeValidator $canUpgradeValidator,
        private readonly UsageLimitsValidator $usageLimitsValidator,
    ) {}

    /**
     * Build validation chain for downgrade operation.
     */
    public function buildDowngradeChain(): SubscriptionValidator
    {
        $this->subscriptionExistsValidator->setNext($this->planExistsValidator);
        $this->planExistsValidator->setNext($this->canDowngradeValidator);
        $this->canDowngradeValidator->setNext($this->usageLimitsValidator);

        return $this->subscriptionExistsValidator;
    }

    /**
     * Build validation chain for upgrade operation.
     */
    public function buildUpgradeChain(): SubscriptionValidator
    {
        $this->subscriptionExistsValidator->setNext($this->planExistsValidator);
        $this->planExistsValidator->setNext($this->canUpgradeValidator);

        return $this->subscriptionExistsValidator;
    }
}
