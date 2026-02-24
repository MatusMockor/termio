<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepository
{
    public function find(int $id): ?Service;

    public function findOrFail(int $id): Service;

    public function create(array $data): Service;

    public function update(Service $service, array $data): Service;

    public function delete(Service $service): void;

    public function getMaxSortOrder(): int;

    public function updateSortOrder(int $id, int $sortOrder): void;

    /**
     * @return Collection<int, Service>
     */
    public function getAllOrdered(): Collection;

    /**
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginateOrdered(int $perPage): LengthAwarePaginator;

    /**
     * Find a service by ID without tenant scope (for public booking).
     */
    public function findByIdWithoutTenantScope(int $id, int $tenantId): Service;
}
