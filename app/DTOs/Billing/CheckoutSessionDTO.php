<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

final readonly class CheckoutSessionDTO
{
    public function __construct(
        public string $url,
        public string $sessionId,
    ) {}
}
