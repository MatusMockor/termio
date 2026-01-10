<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Contracts\Repositories\StaffRepository;
use App\DTOs\Staff\CreateStaffDTO;
use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;

final class StaffCreateAction
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
    ) {}

    public function handle(CreateStaffDTO $dto): StaffProfile
    {
        $maxOrder = $this->staffRepository->getMaxSortOrder();

        return DB::transaction(function () use ($dto, $maxOrder): StaffProfile {
            $staff = $this->staffRepository->create([
                'display_name' => $dto->displayName,
                'bio' => $dto->bio,
                'photo_url' => $dto->photoUrl,
                'specializations' => $dto->specializations,
                'is_bookable' => $dto->isBookable,
                'sort_order' => $maxOrder + 1,
            ]);

            if (count($dto->serviceIds) > 0) {
                $this->staffRepository->syncServices($staff, $dto->serviceIds);
            }

            $staff->load('services:id,name');

            return $staff;
        });
    }
}
