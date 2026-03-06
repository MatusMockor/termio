<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

final readonly class StripeSubscriptionResultDTO
{
    public function __construct(
        public string $id,
        public string $status,
        public ?int $trialEnd = null,
    ) {}
}
