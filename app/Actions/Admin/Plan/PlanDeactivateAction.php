<?php

declare(strict_types=1);

namespace App\Actions\Admin\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\Exceptions\PlanHasActiveSubscribersException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PlanDeactivateAction
{
    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {}

    public function handle(Plan $plan): Plan
    {
        if ($this->planRepository->hasActiveSubscribers($plan)) {
            throw new PlanHasActiveSubscribersException(
                'Cannot deactivate plan with active subscribers. Please migrate subscribers first.'
            );
        }

        return DB::transaction(function () use ($plan): Plan {
            $deactivatedPlan = $this->planRepository->deactivate($plan);

            Log::info('Plan deactivated', [
                'plan_id' => $deactivatedPlan->id,
                'plan_name' => $deactivatedPlan->name,
            ]);

            return $deactivatedPlan;
        });
    }
}
