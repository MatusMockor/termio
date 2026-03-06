<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Billing\CheckoutSessionDTO;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;

interface StripeBillingGatewayContract
{
    public function createPortalSession(string $customerId, string $returnUrl): string;

    /**
     * @param  array<string, mixed>  $params
     */
    public function createCheckoutSession(array $params): CheckoutSessionDTO;

    public function createSubscription(CreateStripeSubscriptionDTO $dto): StripeSubscriptionResultDTO;
}
