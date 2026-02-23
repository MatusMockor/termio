<?php

declare(strict_types=1);

namespace App\DTOs\WorkingHours;

final readonly class WorkingHoursDTO
{
    public function __construct(
        public int $tenantId,
        public ?int $staffId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public int $activeFlag = 1,
    ) {}

    /**
     * @return array{
     *     tenant_id: int,
     *     staff_id: int|null,
     *     day_of_week: int,
     *     start_time: string,
     *     end_time: string,
     *     is_active: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'staff_id' => $this->staffId,
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_active' => $this->activeFlag === 1,
        ];
    }
}
