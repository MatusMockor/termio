<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;

interface StaffRepository
{
    public function find(int $id): ?StaffProfile;

    public function findOrFail(int $id): StaffProfile;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StaffProfile;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StaffProfile $staff, array $data): StaffProfile;

    public function delete(StaffProfile $staff): void;

    public function getMaxSortOrder(): int;

    public function updateSortOrder(int $id, int $sortOrder): void;

    /**
     * @param  array<int, int>  $serviceIds
     */
    public function syncServices(StaffProfile $staff, array $serviceIds): void;

    /**
     * @return Collection<int, StaffProfile>
     */
    public function getAllOrdered(): Collection;
}
