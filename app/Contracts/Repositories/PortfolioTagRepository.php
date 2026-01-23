<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\PortfolioTag;
use Illuminate\Database\Eloquent\Collection;

interface PortfolioTagRepository
{
    public function find(int $id): ?PortfolioTag;

    public function findOrFail(int $id): PortfolioTag;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PortfolioTag;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PortfolioTag $tag, array $data): PortfolioTag;

    public function delete(PortfolioTag $tag): void;

    /**
     * @return Collection<int, PortfolioTag>
     */
    public function getAll(): Collection;

    /**
     * @return Collection<int, PortfolioTag>
     */
    public function getByTenantWithoutScope(int $tenantId): Collection;

    public function findBySlug(string $slug): ?PortfolioTag;
}
