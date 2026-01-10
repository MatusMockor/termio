<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Contracts\Repositories\StaffRepository;
use Illuminate\Support\Facades\DB;

final class StaffReorderAction
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
    ) {}

    /**
     * @param  array<int, int>  $order
     */
    public function handle(array $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($order as $position => $staffId) {
                $this->staffRepository->updateSortOrder($staffId, $position);
            }
        });
    }
}
