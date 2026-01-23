<?php

declare(strict_types=1);

namespace App\DTOs\Portfolio;

final readonly class UpdatePortfolioTagDTO
{
    public function __construct(
        public string $name,
        public ?string $color,
    ) {}
}
