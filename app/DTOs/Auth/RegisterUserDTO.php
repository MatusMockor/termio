<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Enums\BusinessType;

final readonly class RegisterUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $businessName,
        public ?BusinessType $businessType,
    ) {}
}
