<?php

declare(strict_types=1);

namespace App\Actions\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\Models\Plan;
use Illuminate\Support\Collection;

final class GetPublicPlansAction
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    /**
     * @return Collection<int, Plan>
     */
    public function handle(): Collection
    {
        return $this->plans->getPublic();
    }
}
