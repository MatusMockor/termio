<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class ClientMetricsDTO
{
    public function __construct(
        public int $newClients,
        public int $returningClients,
        public float $newClientRatio,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'new_clients' => $this->newClients,
            'returning_clients' => $this->returningClients,
            'new_client_ratio' => round($this->newClientRatio, 2),
        ];
    }
}
