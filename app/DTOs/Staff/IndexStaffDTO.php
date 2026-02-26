<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class IndexStaffDTO
{
    public function __construct(
        public int $perPage,
    ) {}
}
