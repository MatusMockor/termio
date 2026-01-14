<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class ReportMetricsDTO
{
    public function __construct(
        public float $totalRevenue,
        public AppointmentMetricsDTO $appointments,
        public ClientMetricsDTO $clients,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_revenue' => round($this->totalRevenue, 2),
            ...$this->appointments->toArray(),
            ...$this->clients->toArray(),
        ];
    }
}
