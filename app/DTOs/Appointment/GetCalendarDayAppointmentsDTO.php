<?php

declare(strict_types=1);

namespace App\DTOs\Appointment;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;

final readonly class GetCalendarDayAppointmentsDTO
{
    /**
     * @param  array<int, string>  $relations
     */
    public function __construct(
        public Carbon $date,
        public ?int $staffId,
        public ?AppointmentStatus $status,
        public int $offset,
        public int $limit,
        public array $relations = [],
    ) {}
}
