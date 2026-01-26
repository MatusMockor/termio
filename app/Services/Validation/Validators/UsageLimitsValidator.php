<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Contracts\Services\UsageValidationServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Services\Validation\AbstractSubscriptionValidator;

final class UsageLimitsValidator extends AbstractSubscriptionValidator
{
    public function __construct(
        private readonly UsageValidationServiceContract $usageValidation,
    ) {}

    protected function doValidate(ValidationContext $context): void
    {
        if ($context->tenant === null || $context->plan === null) {
            return;
        }

        $violations = $this->usageValidation->checkLimitViolations(
            $context->tenant,
            $context->plan,
        );

        if (! $violations) {
            return;
        }

        throw SubscriptionException::usageExceedsLimits($violations);
    }
}
