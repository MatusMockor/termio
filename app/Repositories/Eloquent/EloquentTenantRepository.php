<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\TenantRepository;
use App\Models\Tenant;

final class EloquentTenantRepository implements TenantRepository
{
    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh() ?? $tenant;
    }
}
