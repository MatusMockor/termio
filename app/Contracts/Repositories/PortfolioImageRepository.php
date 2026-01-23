<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\PortfolioImage;
use Illuminate\Database\Eloquent\Collection;

interface PortfolioImageRepository
{
    public function find(int $id): ?PortfolioImage;

    public function findOrFail(int $id): PortfolioImage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PortfolioImage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PortfolioImage $image, array $data): PortfolioImage;

    public function delete(PortfolioImage $image): void;

    public function getMaxSortOrder(int $staffId): int;

    public function updateSortOrder(int $id, int $sortOrder): void;

    /**
     * @return Collection<int, PortfolioImage>
     */
    public function getAllOrdered(): Collection;

    /**
     * @return Collection<int, PortfolioImage>
     */
    public function getByStaffOrdered(int $staffId): Collection;

    /**
     * @return Collection<int, PortfolioImage>
     */
    public function getPublicByTenant(int $tenantId): Collection;

    /**
     * @return Collection<int, PortfolioImage>
     */
    public function getPublicByStaff(int $tenantId, int $staffId): Collection;

    /**
     * @param  array<int>  $tagIds
     * @return Collection<int, PortfolioImage>
     */
    public function getPublicByTags(int $tenantId, array $tagIds): Collection;
}
