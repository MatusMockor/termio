<?php

declare(strict_types=1);

namespace App\Actions\Admin\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Admin\UpdatePlanDTO;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PlanUpdateAction
{
    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {}

    public function handle(Plan $plan, UpdatePlanDTO $dto): Plan
    {
        $data = $dto->toArray();

        if (!$data) {
            return $plan;
        }

        return DB::transaction(function () use ($plan, $data): Plan {
            $updatedPlan = $this->planRepository->update($plan, $data);

            Log::info('Plan updated', [
                'plan_id' => $updatedPlan->id,
                'plan_name' => $updatedPlan->name,
                'updated_fields' => array_keys($data),
            ]);

            return $updatedPlan;
        });
    }
}
