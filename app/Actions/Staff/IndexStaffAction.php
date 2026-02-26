<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Contracts\Repositories\StaffRepository;
use App\DTOs\Staff\IndexStaffDTO;
use App\Models\StaffProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexStaffAction
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, StaffProfile>
     */
    public function handle(IndexStaffDTO $dto): LengthAwarePaginator
    {
        return $this->staffRepository->paginateOrdered($dto->perPage);
    }
}
