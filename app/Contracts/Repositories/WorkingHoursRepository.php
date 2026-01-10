<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Collection;

interface WorkingHoursRepository
{
    public function find(int $id): ?WorkingHours;

    public function findOrFail(int $id): WorkingHours;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkingHours;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkingHours $workingHours, array $data): WorkingHours;

    public function delete(WorkingHours $workingHours): void;

    public function deleteByStaffId(int $staffId): void;

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getByStaffIdOrdered(int $staffId): Collection;

    /**
     * @return Collection<int, WorkingHours>
     */
    public function getByTenantAndStaff(int $tenantId, ?int $staffId): Collection;

    public function deleteByTenantAndStaff(int $tenantId, ?int $staffId): int;
}
