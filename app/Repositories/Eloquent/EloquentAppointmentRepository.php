<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AppointmentRepository;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class EloquentAppointmentRepository implements AppointmentRepository
{
    public function create(array $data): Appointment
    {
        return Appointment::create($data);
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $appointment->update($data);

        return $appointment;
    }

    public function delete(Appointment $appointment): void
    {
        $appointment->delete();
    }

    /**
     * @param  array<string>  $relations
     */
    public function loadRelations(Appointment $appointment, array $relations): Appointment
    {
        $appointment->load($relations);

        return $appointment;
    }

    /**
     * @param  array<string>  $relations
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function findFiltered(
        ?Carbon $date,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?int $staffId,
        ?AppointmentStatus $status,
        int $perPage,
        array $relations = []
    ): LengthAwarePaginator {
        $query = Appointment::query();

        if ($relations) {
            $query->with($relations);
        }

        if ($date !== null) {
            $this->applyDateFilters($query, $date, $staffId, $status);
        }

        if ($date === null && $startDate !== null && $endDate !== null) {
            $this->applyDateRangeFilters($query, $startDate, $endDate, $staffId, $status);
        }

        if ($date === null && ! ($startDate !== null && $endDate !== null) && $staffId !== null) {
            $query->forStaff($staffId);
        }

        if ($date === null && ! ($startDate !== null && $endDate !== null) && $status !== null) {
            $query->withStatus($status->value);
        }

        return $query->orderBy('starts_at')->paginate($perPage);
    }

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Appointment>
     */
    public function findCalendarByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        int $perDay,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
        array $relations = []
    ): Collection {
        $baseQuery = Appointment::query()
            ->select('appointments.*')
            ->selectRaw('DATE(starts_at) as calendar_date')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY DATE(starts_at) ORDER BY starts_at, id) as calendar_row_number');

        $this->applyDateRangeFilters($baseQuery, $startDate, $endDate, $staffId, $status);

        $query = Appointment::query()->fromSub($baseQuery, 'appointments');

        if ($relations) {
            $query->with($relations);
        }

        return $query
            ->whereRaw('appointments.calendar_row_number <= ?', [$perDay])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function countCalendarByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
    ): array {
        $query = Appointment::query()
            ->selectRaw('DATE(starts_at) as calendar_date')
            ->selectRaw('COUNT(*) as total_count');

        $this->applyDateRangeFilters($query, $startDate, $endDate, $staffId, $status);

        /** @var \Illuminate\Support\Collection<int, object{calendar_date: string, total_count: int|string}> $rows */
        $rows = $query
            ->toBase()
            ->groupByRaw('DATE(starts_at)')
            ->orderByRaw('DATE(starts_at)')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->calendar_date] = (int) $row->total_count;
        }

        return $counts;
    }

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Appointment>
     */
    public function findForDatePaginated(
        Carbon $date,
        int $limit,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
        int $offset = 0,
        array $relations = []
    ): Collection {
        $query = Appointment::query();

        if ($relations) {
            $query->with($relations);
        }

        $this->applyDateFilters($query, $date, $staffId, $status);

        return $query
            ->orderBy('starts_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function countForDate(
        Carbon $date,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
    ): int {
        $query = Appointment::query();

        $this->applyDateFilters($query, $date, $staffId, $status);

        return $query->count();
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private function applyDateRangeFilters(
        Builder $query,
        Carbon $startDate,
        Carbon $endDate,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
    ): Builder {
        $query->forDateRange($startDate->copy(), $endDate->copy());

        if ($staffId !== null) {
            $query->forStaff($staffId);
        }

        if ($status !== null) {
            $query->withStatus($status->value);
        }

        return $query;
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private function applyDateFilters(
        Builder $query,
        Carbon $date,
        ?int $staffId = null,
        ?AppointmentStatus $status = null,
    ): Builder {
        $query->forDate($date->copy());

        if ($staffId !== null) {
            $query->forStaff($staffId);
        }

        if ($status !== null) {
            $query->withStatus($status->value);
        }

        return $query;
    }
}
