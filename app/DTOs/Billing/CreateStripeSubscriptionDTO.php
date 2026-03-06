<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

final readonly class CreateStripeSubscriptionDTO
{
    public function __construct(
        public string $customerId,
        public string $priceId,
        public string $defaultPaymentMethodId,
        public ?int $trialPeriodDays = null,
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'customer' => $this->customerId,
            'items' => [
                ['price' => $this->priceId],
            ],
            'default_payment_method' => $this->defaultPaymentMethodId,
        ];

        if ($this->trialPeriodDays) {
            $payload['trial_period_days'] = $this->trialPeriodDays;
        }

        return $payload;
    }

    /**
     * @return array{idempotency_key?: string}
     */
    public function toOptions(): array
    {
        if (! $this->idempotencyKey) {
            return [];
        }

        return [
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
