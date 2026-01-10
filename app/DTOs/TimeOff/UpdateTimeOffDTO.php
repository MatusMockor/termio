<?php

declare(strict_types=1);

namespace App\DTOs\TimeOff;

final readonly class UpdateTimeOffDTO
{
    public function __construct(
        public ?int $staffId,
        public ?string $date,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $reason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'staff_id' => $this->staffId,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'reason' => $this->reason,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
