<?php

declare(strict_types=1);

namespace App\DTOs\Booking;

final readonly class CreatePublicBookingDTO
{
    public function __construct(
        public int $serviceId,
        public ?int $staffId,
        public string $startsAt,
        public string $clientName,
        public string $clientPhone,
        public string $clientEmail,
        public ?string $notes,
    ) {}
}
