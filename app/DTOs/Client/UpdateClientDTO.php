<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class UpdateClientDTO
{
    public function __construct(
        public ?string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $notes,
        public ?string $status,
    ) {}
}
