<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class RequiredPlanDTO
{
    public function __construct(
        public ?string $name,
        public string $slug,
        public ?string $monthlyPrice,
    ) {}

    /**
     * @return array{name: string|null, slug: string, monthly_price: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'monthly_price' => $this->monthlyPrice,
        ];
    }
}
