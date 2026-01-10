<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Tenant;

interface TenantRepository
{
    public function create(array $data): Tenant;

    public function findBySlug(string $slug): ?Tenant;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant;
}
