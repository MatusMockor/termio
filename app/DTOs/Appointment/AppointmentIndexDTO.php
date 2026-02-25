<?php

declare(strict_types=1);

namespace App\DTOs\Appointment;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;

final readonly class AppointmentIndexDTO
{
    /**
     * @param  array<int, string>  $relations
     */
    public function __construct(
        public ?Carbon $date,
        public ?Carbon $startDate,
        public ?Carbon $endDate,
        public ?int $staffId,
        public ?AppointmentStatus $status,
        public int $perPage,
        public array $relations = [],
    ) {}
}
