<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

final readonly class UpdateBrandingDTO
{
    public function __construct(
        public string $primaryColor,
    ) {}
}
