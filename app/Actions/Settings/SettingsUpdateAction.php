<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Contracts\Repositories\TenantRepository;
use App\DTOs\Settings\UpdateSettingsDTO;
use App\Models\Tenant;

final class SettingsUpdateAction
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {}

    public function handle(Tenant $tenant, UpdateSettingsDTO $dto): Tenant
    {
        $data = array_filter([
            'name' => $dto->name,
            'business_type' => $dto->businessType,
            'address' => $dto->address,
            'phone' => $dto->phone,
            'timezone' => $dto->timezone,
            'reservation_lead_time_hours' => $dto->reservationLeadTimeHours,
            'reservation_max_days_in_advance' => $dto->reservationMaxDaysInAdvance,
            'reservation_slot_interval_minutes' => $dto->reservationSlotIntervalMinutes,
            'settings' => $dto->settings,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->tenantRepository->update($tenant, $data);
    }
}
