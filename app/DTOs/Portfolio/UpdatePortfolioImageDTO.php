<?php

declare(strict_types=1);

namespace App\DTOs\Portfolio;

final readonly class UpdatePortfolioImageDTO
{
    /**
     * @param  array<int>  $tagIds
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $tagIds,
        public bool $isPublic,
    ) {}
}
