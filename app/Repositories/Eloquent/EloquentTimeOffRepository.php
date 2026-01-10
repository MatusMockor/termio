<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\TimeOffRepository;
use App\Models\TimeOff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTimeOffRepository implements TimeOffRepository
{
    public function find(int $id): ?TimeOff
    {
        return TimeOff::find($id);
    }

    public function findOrFail(int $id): TimeOff
    {
        return TimeOff::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TimeOff
    {
        return TimeOff::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TimeOff $timeOff, array $data): TimeOff
    {
        $timeOff->update($data);

        return $timeOff;
    }

    public function delete(TimeOff $timeOff): void
    {
        $timeOff->delete();
    }

    /**
     * @return Collection<int, TimeOff>
     */
    public function getAllOrderedByDate(): Collection
    {
        return TimeOff::with('staff')->orderBy('date')->get();
    }

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByStaff(int $staffId): Collection
    {
        return TimeOff::with('staff')
            ->forStaff($staffId)
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return TimeOff::with('staff')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, TimeOff>
     */
    public function getByDate(Carbon $date): Collection
    {
        return TimeOff::with('staff')
            ->forDate($date)
            ->orderBy('date')
            ->get();
    }
}
