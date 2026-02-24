<?php

declare(strict_types=1);

namespace App\DTOs\Appointment;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;

final readonly class GetCalendarAppointmentsDTO
{
    /**
     * @param  array<int, string>  $relations
     */
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public ?int $staffId,
        public ?AppointmentStatus $status,
        public int $perDay,
        public array $relations = [],
    ) {}
}
