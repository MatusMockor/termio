<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

final readonly class UpdateSettingsDTO
{
    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function __construct(
        public ?string $name,
        public ?string $businessType,
        public ?string $address,
        public ?string $phone,
        public ?string $timezone,
        public ?int $reservationLeadTimeHours,
        public ?int $reservationMaxDaysInAdvance,
        public ?int $reservationSlotIntervalMinutes,
        public ?array $settings,
    ) {}
}
