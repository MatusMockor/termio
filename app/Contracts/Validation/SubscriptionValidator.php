<?php

declare(strict_types=1);

namespace App\Contracts\Validation;

use App\DTOs\Subscription\ValidationContext;

interface SubscriptionValidator
{
    /**
     * Set the next validator in the chain.
     */
    public function setNext(SubscriptionValidator $validator): SubscriptionValidator;

    /**
     * Validate the context and pass to next validator if valid.
     *
     * @throws \App\Exceptions\SubscriptionException
     */
    public function validate(ValidationContext $context): void;
}
