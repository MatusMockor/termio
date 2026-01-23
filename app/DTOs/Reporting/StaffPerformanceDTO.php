<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class StaffPerformanceDTO
{
    public function __construct(
        public int $staffId,
        public string $staffName,
        public float $revenue,
        public int $appointmentCount,
        public int $completedCount,
        public float $occupancyRate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'staff_id' => $this->staffId,
            'staff_name' => $this->staffName,
            'revenue' => round($this->revenue, 2),
            'appointment_count' => $this->appointmentCount,
            'completed_count' => $this->completedCount,
            'occupancy_rate' => round($this->occupancyRate, 2),
        ];
    }
}
