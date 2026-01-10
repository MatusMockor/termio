<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AppointmentRepository;
use App\Models\Appointment;
use Carbon\Carbon;
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
     * @return Collection<int, Appointment>
     */
    public function findFiltered(
        ?Carbon $date = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $staffId = null,
        ?string $status = null,
        array $relations = []
    ): Collection {
        $query = Appointment::query();

        if (count($relations) > 0) {
            $query->with($relations);
        }

        if ($date !== null) {
            $query->forDate($date);
        }

        if ($startDate !== null && $endDate !== null) {
            $query->forDateRange($startDate, $endDate);
        }

        if ($staffId !== null) {
            $query->forStaff($staffId);
        }

        if ($status !== null) {
            $query->withStatus($status);
        }

        return $query->orderBy('starts_at')->get();
    }
}
