<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PortfolioImageRepository;
use App\Models\PortfolioImage;
use Illuminate\Database\Eloquent\Collection;

final class EloquentPortfolioImageRepository implements PortfolioImageRepository
{
    public function find(int $id): ?PortfolioImage
    {
        return PortfolioImage::find($id);
    }

    public function findOrFail(int $id): PortfolioImage
    {
        return PortfolioImage::findOrFail($id);
    }

    public function create(array $data): PortfolioImage
    {
        return PortfolioImage::create($data);
    }

    public function update(PortfolioImage $image, array $data): PortfolioImage
    {
        $image->update($data);

        return $image;
    }

    public function delete(PortfolioImage $image): void
    {
        $image->delete();
    }

    public function getMaxSortOrder(int $staffId): int
    {
        return PortfolioImage::where('staff_id', $staffId)->max('sort_order') ?? 0;
    }

    public function updateSortOrder(int $id, int $sortOrder): void
    {
        PortfolioImage::where('id', $id)->update(['sort_order' => $sortOrder]);
    }

    public function getAllOrdered(): Collection
    {
        return PortfolioImage::with(['tags', 'staff'])
            ->ordered()
            ->get();
    }

    public function getByStaffOrdered(int $staffId): Collection
    {
        return PortfolioImage::with('tags')
            ->forStaff($staffId)
            ->ordered()
            ->get();
    }

    public function getPublicByTenant(int $tenantId): Collection
    {
        return PortfolioImage::withoutTenantScope()
            ->with(['tags', 'staff'])
            ->where('tenant_id', $tenantId)
            ->public()
            ->ordered()
            ->get();
    }

    public function getPublicByStaff(int $tenantId, int $staffId): Collection
    {
        return PortfolioImage::withoutTenantScope()
            ->with('tags')
            ->where('tenant_id', $tenantId)
            ->forStaff($staffId)
            ->public()
            ->ordered()
            ->get();
    }

    public function getPublicByTags(int $tenantId, array $tagIds): Collection
    {
        return PortfolioImage::withoutTenantScope()
            ->with(['tags', 'staff'])
            ->where('tenant_id', $tenantId)
            ->public()
            ->withTags($tagIds)
            ->ordered()
            ->get();
    }
}
