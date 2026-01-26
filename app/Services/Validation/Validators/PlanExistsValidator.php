<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Services\Validation\AbstractSubscriptionValidator;

final class PlanExistsValidator extends AbstractSubscriptionValidator
{
    protected function doValidate(ValidationContext $context): void
    {
        if ($context->plan !== null) {
            return;
        }

        throw SubscriptionException::planNotFound($context->planId ?? 0);
    }
}
