<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

final readonly class VatCalculation
{
    public function __construct(
        public float $netAmount,
        public float $vatRate,
        public float $vatAmount,
        public float $grossAmount,
        public bool $reverseCharge,
        public ?string $note = null,
    ) {}
}
