<?php

declare(strict_types=1);

namespace App\DTOs\TimeOff;

final readonly class CreateTimeOffDTO
{
    public function __construct(
        public ?int $staffId,
        public string $date,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $reason,
    ) {}
}
