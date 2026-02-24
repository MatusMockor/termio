<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AppointmentRepository
{
    public function create(array $data): Appointment;

    public function update(Appointment $appointment, array $data): Appointment;

    public function delete(Appointment $appointment): void;

    /**
     * @param  array<string>  $relations
     */
    public function loadRelations(Appointment $appointment, array $relations): Appointment;

    /**
     * @param  array<string>  $relations
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function findFiltered(
        ?Carbon $date,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?int $staffId,
        ?string $status,
        int $perPage,
        array $relations = []
    ): LengthAwarePaginator;

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Appointment>
     */
    public function findCalendarByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?int $staffId = null,
        ?string $status = null,
        int $perDay = 4,
        array $relations = []
    ): Collection;

    /**
     * @return array<string, int>
     */
    public function countCalendarByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?int $staffId = null,
        ?string $status = null,
    ): array;

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Appointment>
     */
    public function findForDatePaginated(
        Carbon $date,
        ?int $staffId = null,
        ?string $status = null,
        int $offset = 0,
        int $limit = 4,
        array $relations = []
    ): Collection;

    public function countForDate(
        Carbon $date,
        ?int $staffId = null,
        ?string $status = null,
    ): int;
}
