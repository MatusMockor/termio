<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceRepository;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

final class EloquentServiceRepository implements ServiceRepository
{
    public function find(int $id): ?Service
    {
        return Service::find($id);
    }

    public function findOrFail(int $id): Service
    {
        return Service::findOrFail($id);
    }

    public function create(array $data): Service
    {
        return Service::create($data);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    public function getMaxSortOrder(): int
    {
        return Service::max('sort_order') ?? 0;
    }

    public function updateSortOrder(int $id, int $sortOrder): void
    {
        Service::where('id', $id)->update(['sort_order' => $sortOrder]);
    }

    /**
     * @return Collection<int, Service>
     */
    public function getAllOrdered(): Collection
    {
        return Service::ordered()->get();
    }

    public function findByIdWithoutTenantScope(int $id, int $tenantId): Service
    {
        return Service::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
