<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

final readonly class CreatePlanDTO
{
    /**
     * @param  array{monthly: float, yearly: float}  $pricing
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $limits
     * @param  array{is_active: bool, is_public: bool, sort_order: int}  $visibility
     * @param  array{monthly_price_id: string|null, yearly_price_id: string|null}  $stripeConfig
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public array $pricing,
        public array $features,
        public array $limits,
        public array $visibility,
        public array $stripeConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->pricing['monthly'],
            'yearly_price' => $this->pricing['yearly'],
            'features' => $this->features,
            'limits' => $this->limits,
            'sort_order' => $this->visibility['sort_order'],
            'is_active' => $this->visibility['is_active'],
            'is_public' => $this->visibility['is_public'],
            'stripe_monthly_price_id' => $this->stripeConfig['monthly_price_id'],
            'stripe_yearly_price_id' => $this->stripeConfig['yearly_price_id'],
        ];
    }
}
