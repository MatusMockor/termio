<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Services\Validation\AbstractSubscriptionValidator;

final class SubscriptionExistsValidator extends AbstractSubscriptionValidator
{
    protected function doValidate(ValidationContext $context): void
    {
        if ($context->subscription !== null) {
            return;
        }

        throw SubscriptionException::subscriptionNotFound($context->subscriptionId ?? 0);
    }
}
