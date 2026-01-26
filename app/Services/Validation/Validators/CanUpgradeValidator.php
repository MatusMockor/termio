<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Services\Validation\AbstractSubscriptionValidator;

final class CanUpgradeValidator extends AbstractSubscriptionValidator
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
    ) {}

    protected function doValidate(ValidationContext $context): void
    {
        if ($context->tenant === null || $context->plan === null || $context->subscription === null) {
            return;
        }

        if ($this->subscriptionService->canUpgradeTo($context->tenant, $context->plan)) {
            return;
        }

        throw SubscriptionException::cannotUpgrade(
            $context->subscription->plan,
            $context->plan,
        );
    }
}
