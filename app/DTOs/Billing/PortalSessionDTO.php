<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

final readonly class PortalSessionDTO
{
    public function __construct(
        public string $url,
    ) {}

    /**
     * @return array{url: string}
     */
    public function toArray(): array
    {
        return ['url' => $this->url];
    }
}
