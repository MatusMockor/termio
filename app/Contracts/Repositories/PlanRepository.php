<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan;

    public function findById(int $id): ?Plan;

    public function findBySlug(string $slug): ?Plan;

    /**
     * @return Collection<int, Plan>
     */
    public function getActive(): Collection;

    /**
     * @return Collection<int, Plan>
     */
    public function getPublic(): Collection;

    public function getFreePlan(): ?Plan;
}
