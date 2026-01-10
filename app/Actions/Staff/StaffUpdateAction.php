<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Contracts\Repositories\StaffRepository;
use App\DTOs\Staff\UpdateStaffDTO;
use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;

final class StaffUpdateAction
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
    ) {}

    public function handle(StaffProfile $staff, UpdateStaffDTO $dto): StaffProfile
    {
        return DB::transaction(function () use ($staff, $dto): StaffProfile {
            $data = $dto->toArray();

            if (count($data) > 0) {
                $this->staffRepository->update($staff, $data);
            }

            if ($dto->hasServiceIds && $dto->serviceIds !== null) {
                $this->staffRepository->syncServices($staff, $dto->serviceIds);
            }

            $staff->load('services:id,name');

            return $staff;
        });
    }
}
