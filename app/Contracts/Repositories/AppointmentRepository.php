<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Appointment;
use Carbon\Carbon;
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
     * @return Collection<int, Appointment>
     */
    public function findFiltered(
        ?Carbon $date = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $staffId = null,
        ?string $status = null,
        array $relations = []
    ): Collection;
}
