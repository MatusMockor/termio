<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\TimeOff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface TimeOffRepository
{
    public function find(int $id): ?TimeOff;

    public function findOrFail(int $id): TimeOff;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TimeOff;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TimeOff $timeOff, array $data): TimeOff;

    public function delete(TimeOff $timeOff): void;

    /**
     * @return Collection<int, TimeOff>
     */
    public function getAllOrderedByDate(): Collection;

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByStaff(int $staffId): Collection;

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByDateRange(Carbon $startDate, Carbon $endDate): Collection;

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByDate(Carbon $date): Collection;
}
