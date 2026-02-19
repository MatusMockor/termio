<?php

declare(strict_types=1);

namespace App\Enums;

enum UsageResource: string
{
    case Reservations = 'reservations';
    case Users = 'users';
    case Services = 'services';
    case Clients = 'clients';

    public function planLimitKey(): string
    {
        return match ($this) {
            self::Reservations => 'reservations_per_month',
            default => $this->value,
        };
    }

    public function defaultLimit(): int
    {
        return match ($this) {
            self::Clients => (int) config('subscription.default_limits.clients', 100),
            default => (int) config('subscription.default_limits.'.$this->value, 0),
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Reservations => 'Reservation',
            self::Users => 'User',
            self::Services => 'Service',
            self::Clients => 'Client',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function usageStatsResources(): array
    {
        return [self::Reservations, self::Users, self::Services];
    }

    /**
     * @return array<int, self>
     */
    public static function planValidationResources(): array
    {
        return [self::Users, self::Services, self::Clients];
    }
}
