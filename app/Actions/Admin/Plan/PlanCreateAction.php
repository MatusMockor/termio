<?php

declare(strict_types=1);

namespace App\Actions\Admin\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Admin\CreatePlanDTO;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PlanCreateAction
{
    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {}

    public function handle(CreatePlanDTO $dto): Plan
    {
        return DB::transaction(function () use ($dto): Plan {
            $plan = $this->planRepository->create($dto->toArray());

            Log::info('Plan created', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
            ]);

            return $plan;
        });
    }
}
