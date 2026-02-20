<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface BookingAvailability
{
    /**
     * @return array<int, array{time: string, available: bool}|array{time: string, available: bool, staff_id: int}>
     */
    public function getAvailability(Tenant $tenant, int $serviceId, string $date, ?int $staffId = null): array;
}
