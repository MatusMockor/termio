<?php

declare(strict_types=1);

namespace App\DTOs\Client;

use App\Enums\ClientStatus;

final readonly class IndexClientsDTO
{
    public function __construct(
        public ?ClientStatus $status,
        public int $perPage,
    ) {}
}
