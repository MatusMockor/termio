<?php

declare(strict_types=1);

namespace App\Actions\TimeOff;

use App\Contracts\Repositories\TimeOffRepository;
use App\DTOs\TimeOff\UpdateTimeOffDTO;
use App\Models\TimeOff;

final class TimeOffUpdateAction
{
    public function __construct(
        private readonly TimeOffRepository $timeOffRepository,
    ) {}

    public function handle(TimeOff $timeOff, UpdateTimeOffDTO $dto): TimeOff
    {
        $timeOff = $this->timeOffRepository->update($timeOff, $dto->toArray());

        $timeOff->load('staff');

        return $timeOff;
    }
}
