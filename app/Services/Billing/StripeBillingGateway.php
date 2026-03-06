<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\DTOs\Billing\CheckoutSessionDTO;
use App\DTOs\Billing\CreateStripeSubscriptionDTO;
use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\Exceptions\BillingProviderException;
use Throwable;

final class StripeBillingGateway implements StripeBillingGatewayContract
{
    public function __construct(
        private readonly StripeServiceContract $stripeService,
    ) {}

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        try {
            return $this->stripeService->createBillingPortalSession($customerId, $returnUrl);
        } catch (Throwable $exception) {
            throw BillingProviderException::fromThrowable($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createCheckoutSession(array $params): CheckoutSessionDTO
    {
        try {
            $session = $this->stripeService->createCheckoutSession($params);
        } catch (Throwable $exception) {
            throw BillingProviderException::fromThrowable($exception);
        }

        return new CheckoutSessionDTO(
            url: (string) $session->url,
            sessionId: $session->id,
        );
    }

    public function createSubscription(CreateStripeSubscriptionDTO $dto): StripeSubscriptionResultDTO
    {
        try {
            $subscription = $this->stripeService->createSubscription(
                $dto->toPayload(),
                $dto->toOptions(),
            );
        } catch (Throwable $exception) {
            throw BillingProviderException::fromThrowable($exception);
        }

        $trialEnd = $subscription->trial_end ?? null;

        return new StripeSubscriptionResultDTO(
            id: $subscription->id,
            status: $subscription->status,
            trialEnd: is_int($trialEnd) ? $trialEnd : null,
        );
    }
}
