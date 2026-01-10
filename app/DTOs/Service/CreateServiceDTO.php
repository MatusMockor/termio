<?php

declare(strict_types=1);

namespace App\DTOs\Service;

final readonly class CreateServiceDTO
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $durationMinutes,
        public float $price,
        public ?string $category,
        public bool $isActive,
        public bool $isBookableOnline,
    ) {}
}
