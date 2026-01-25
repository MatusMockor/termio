<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessType: string
{
    case HairBeauty = 'hair_beauty';
    case SpaWellness = 'spa_wellness';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HairBeauty => 'Hair & Beauty',
            self::SpaWellness => 'Spa & Wellness',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HairBeauty => '💈',
            self::SpaWellness => '🧖',
            self::Other => '📋',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HairBeauty => 'Barber shops • Salons • Nails • Makeup',
            self::SpaWellness => 'Massage • Facials • Body Treatments',
            self::Other => 'Other type of business',
        };
    }
}
