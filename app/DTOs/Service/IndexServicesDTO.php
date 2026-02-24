<?php

declare(strict_types=1);

namespace App\DTOs\Service;

final readonly class IndexServicesDTO
{
    public function __construct(
        public int $perPage,
    ) {}
}
