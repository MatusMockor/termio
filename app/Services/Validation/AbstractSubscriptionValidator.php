<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Contracts\Validation\SubscriptionValidator;
use App\DTOs\Subscription\ValidationContext;

abstract class AbstractSubscriptionValidator implements SubscriptionValidator
{
    private ?SubscriptionValidator $nextValidator = null;

    public function setNext(SubscriptionValidator $validator): SubscriptionValidator
    {
        $this->nextValidator = $validator;

        return $validator;
    }

    public function validate(ValidationContext $context): void
    {
        $this->doValidate($context);

        if ($this->nextValidator === null) {
            return;
        }

        $this->nextValidator->validate($context);
    }

    /**
     * Perform the actual validation.
     *
     * @throws \App\Exceptions\SubscriptionException
     */
    abstract protected function doValidate(ValidationContext $context): void;
}
