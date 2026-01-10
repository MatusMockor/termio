<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class GoogleUserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $token,
        public ?string $refreshToken,
    ) {}
}
