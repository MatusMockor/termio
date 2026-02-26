<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\Models\Plan;
use Illuminate\Support\Collection;

final readonly class PlanComparisonMatrixDTO
{
    /**
     * @param  Collection<int, Plan>  $plans
     * @param  array<string, array{label: string, category: string, availability: array<string, bool|string>}>  $features
     */
    public function __construct(
        public Collection $plans,
        public array $features,
    ) {}
}
