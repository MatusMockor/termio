<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Contracts\Repositories\WorkingHoursRepository;
use App\Models\StaffProfile;
use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class StaffWorkingHoursUpdateAction
{
    public function __construct(
        private readonly WorkingHoursRepository $workingHoursRepository,
    ) {}

    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string, is_active: bool}>  $workingHoursData
     * @return Collection<int, WorkingHours>
     */
    public function handle(StaffProfile $staff, array $workingHoursData): Collection
    {
        return DB::transaction(function () use ($staff, $workingHoursData): Collection {
            $this->workingHoursRepository->deleteByStaffId($staff->id);

            foreach ($workingHoursData as $hours) {
                $this->workingHoursRepository->create([
                    'tenant_id' => $staff->tenant_id,
                    'staff_id' => $staff->id,
                    'day_of_week' => $hours['day_of_week'],
                    'start_time' => $hours['start_time'],
                    'end_time' => $hours['end_time'],
                    'is_active' => $hours['is_active'],
                ]);
            }

            return $this->workingHoursRepository->getByStaffIdOrdered($staff->id);
        });
    }
}
