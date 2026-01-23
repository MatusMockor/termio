<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class AppointmentMetricsDTO
{
    public function __construct(
        public int $total,
        public int $completed,
        public int $cancelled,
        public int $noShow,
        public float $occupancyRate,
        public float $noShowRate,
        public float $cancellationRate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'completed' => $this->completed,
            'cancelled' => $this->cancelled,
            'no_show' => $this->noShow,
            'occupancy_rate' => round($this->occupancyRate, 2),
            'no_show_rate' => round($this->noShowRate, 2),
            'cancellation_rate' => round($this->cancellationRate, 2),
        ];
    }
}
