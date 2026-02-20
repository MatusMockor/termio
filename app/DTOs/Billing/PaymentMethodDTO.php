<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Models\PaymentMethod;

final readonly class PaymentMethodDTO
{
    /**
     * @param  array{
     *     brand: string|null,
     *     last4: string|null,
     *     exp_month: int|null,
     *     exp_year: int|null,
     *     is_expired: bool,
     *     is_expiring_soon: bool
     * }  $card
     */
    public function __construct(
        public int $id,
        public string $type,
        public array $card,
        public bool $isDefault,
        public string $createdAt,
    ) {}

    public static function fromModel(PaymentMethod $paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            type: $paymentMethod->type,
            card: [
                'brand' => $paymentMethod->card_brand,
                'last4' => $paymentMethod->card_last4,
                'exp_month' => $paymentMethod->card_exp_month,
                'exp_year' => $paymentMethod->card_exp_year,
                'is_expired' => $paymentMethod->isExpired(),
                'is_expiring_soon' => $paymentMethod->isExpiringSoon(),
            ],
            isDefault: $paymentMethod->is_default,
            createdAt: $paymentMethod->created_at->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'card_brand' => $this->card['brand'],
            'card_last4' => $this->card['last4'],
            'card_exp_month' => $this->card['exp_month'],
            'card_exp_year' => $this->card['exp_year'],
            'is_default' => $this->isDefault,
            'is_expired' => $this->card['is_expired'],
            'is_expiring_soon' => $this->card['is_expiring_soon'],
            'created_at' => $this->createdAt,
        ];
    }
}
