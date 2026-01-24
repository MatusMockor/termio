<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PlanRepository;
use App\Models\Plan;
use Illuminate\Support\Collection;

final class EloquentPlanRepository implements PlanRepository
{
    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->fresh() ?? $plan;
    }

    public function findById(int $id): ?Plan
    {
        return Plan::find($id);
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    public function getActive(): Collection
    {
        return Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPublic(): Collection
    {
        return Plan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getFreePlan(): ?Plan
    {
        return Plan::where('slug', 'free')->first();
    }
}
