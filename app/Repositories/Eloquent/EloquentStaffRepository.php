<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StaffRepository;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;

final class EloquentStaffRepository implements StaffRepository
{
    public function find(int $id): ?StaffProfile
    {
        return StaffProfile::find($id);
    }

    public function findOrFail(int $id): StaffProfile
    {
        return StaffProfile::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StaffProfile
    {
        return StaffProfile::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StaffProfile $staff, array $data): StaffProfile
    {
        $staff->update($data);

        return $staff;
    }

    public function delete(StaffProfile $staff): void
    {
        $staff->delete();
    }

    public function getMaxSortOrder(): int
    {
        return StaffProfile::max('sort_order') ?? 0;
    }

    public function updateSortOrder(int $id, int $sortOrder): void
    {
        StaffProfile::where('id', $id)->update(['sort_order' => $sortOrder]);
    }

    /**
     * @param  array<int, int>  $serviceIds
     */
    public function syncServices(StaffProfile $staff, array $serviceIds): void
    {
        $staff->services()->sync($serviceIds);
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    public function getAllOrdered(): Collection
    {
        return StaffProfile::with('services:id,name')
            ->ordered()
            ->get();
    }
}
