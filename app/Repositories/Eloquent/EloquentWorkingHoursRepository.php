<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WorkingHoursRepository;
use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Collection;

final class EloquentWorkingHoursRepository implements WorkingHoursRepository
{
    public function find(int $id): ?WorkingHours
    {
        return WorkingHours::find($id);
    }

    public function findOrFail(int $id): WorkingHours
    {
        return WorkingHours::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkingHours
    {
        return WorkingHours::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkingHours $workingHours, array $data): WorkingHours
    {
        $workingHours->update($data);

        return $workingHours->fresh() ?? $workingHours;
    }

    public function delete(WorkingHours $workingHours): void
    {
        $workingHours->delete();
    }

    public function deleteByStaffId(int $staffId): void
    {
        WorkingHours::where('staff_id', $staffId)->delete();
    }

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getByStaffIdOrdered(int $staffId): Collection
    {
        return WorkingHours::where('staff_id', $staffId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getByTenantAndStaff(int $tenantId, ?int $staffId): Collection
    {
        return WorkingHours::where('tenant_id', $tenantId)
            ->where('staff_id', $staffId)
            ->get();
    }

    public function deleteByTenantAndStaff(int $tenantId, ?int $staffId): int
    {
        return WorkingHours::where('tenant_id', $tenantId)
            ->where('staff_id', $staffId)
            ->delete();
    }
}
