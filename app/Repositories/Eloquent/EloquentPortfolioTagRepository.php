<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PortfolioTagRepository;
use App\Models\PortfolioTag;
use Illuminate\Database\Eloquent\Collection;

final class EloquentPortfolioTagRepository implements PortfolioTagRepository
{
    public function find(int $id): ?PortfolioTag
    {
        return PortfolioTag::find($id);
    }

    public function findOrFail(int $id): PortfolioTag
    {
        return PortfolioTag::findOrFail($id);
    }

    public function create(array $data): PortfolioTag
    {
        return PortfolioTag::create($data);
    }

    public function update(PortfolioTag $tag, array $data): PortfolioTag
    {
        $tag->update($data);

        return $tag;
    }

    public function delete(PortfolioTag $tag): void
    {
        $tag->delete();
    }

    public function getAll(): Collection
    {
        return PortfolioTag::orderBy('name')->get();
    }

    public function getByTenantWithoutScope(int $tenantId): Collection
    {
        return PortfolioTag::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): ?PortfolioTag
    {
        return PortfolioTag::where('slug', $slug)->first();
    }
}
