<?php

declare(strict_types=1);

namespace App\DTOs\Appointment;

final readonly class CreateAppointmentDTO
{
    public function __construct(
        public int $clientId,
        public int $serviceId,
        public ?int $staffId,
        public string $startsAt,
        public ?string $notes,
        public string $status,
        public string $source,
    ) {}
}
