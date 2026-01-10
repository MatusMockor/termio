<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Contracts\Repositories\WorkingHoursRepository;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SettingsWorkingHoursUpdateAction
{
    public function __construct(
        private readonly WorkingHoursRepository $workingHoursRepository,
    ) {}

    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string, is_active?: bool}>  $workingHours
     * @return Collection<int, \App\Models\WorkingHours>
     */
    public function handle(Tenant $tenant, array $workingHours): Collection
    {
        return DB::transaction(function () use ($tenant, $workingHours): Collection {
            $this->workingHoursRepository->deleteByTenantAndStaff($tenant->id, null);

            foreach ($workingHours as $hours) {
                $this->workingHoursRepository->create([
                    'tenant_id' => $tenant->id,
                    'staff_id' => null,
                    'day_of_week' => $hours['day_of_week'],
                    'start_time' => $hours['start_time'],
                    'end_time' => $hours['end_time'],
                    'is_active' => $hours['is_active'] ?? true,
                ]);
            }

            return $this->workingHoursRepository->getByTenantAndStaff($tenant->id, null);
        });
    }
}
