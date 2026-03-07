<?php

declare(strict_types=1);

namespace App\DTOs\Client;

use App\Enums\ClientBookingState;
use App\Enums\ClientRiskLevel;
use App\Enums\ClientStatus;

final readonly class IndexClientsDTO
{
    public function __construct(
        public ?ClientStatus $status,
        public ?ClientBookingState $bookingState,
        public ?ClientRiskLevel $riskLevel,
        /** @var array<int, int> */
        public array $tagIds,
        public int $perPage,
    ) {}
}
