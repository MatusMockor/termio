<?php

declare(strict_types=1);

namespace App\Actions\TimeOff;

use App\Contracts\Repositories\TimeOffRepository;
use App\DTOs\TimeOff\CreateTimeOffDTO;
use App\Models\TimeOff;

final class TimeOffCreateAction
{
    public function __construct(
        private readonly TimeOffRepository $timeOffRepository,
    ) {}

    public function handle(CreateTimeOffDTO $dto): TimeOff
    {
        $timeOff = $this->timeOffRepository->create([
            'staff_id' => $dto->staffId,
            'date' => $dto->date,
            'start_time' => $dto->startTime,
            'end_time' => $dto->endTime,
            'reason' => $dto->reason,
        ]);

        $timeOff->load('staff');

        return $timeOff;
    }
}
