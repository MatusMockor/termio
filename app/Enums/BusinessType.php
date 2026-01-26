<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessType: string
{
    case Salon = 'salon';
    case Barber = 'barber';
    case Beauty = 'beauty';
    case Massage = 'massage';
    case Fitness = 'fitness';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Salon => 'Salón',
            self::Barber => 'Barbershop',
            self::Beauty => 'Kozmetika',
            self::Massage => 'Masáže',
            self::Fitness => 'Fitness',
            self::Other => 'Iné',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Salon => 'scissors',
            self::Barber => 'razor',
            self::Beauty => 'sparkles',
            self::Massage => 'spa',
            self::Fitness => 'dumbbell',
            self::Other => 'clipboard',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Salon => 'Kaderníctvo • Nechtový dizajn • Styling',
            self::Barber => 'Pánske holičstvo • Strihanie • Úprava fúzov',
            self::Beauty => 'Kozmetické služby • Líčenie • Starostlivosť o pleť',
            self::Massage => 'Masáže • Terapie • Relaxácia',
            self::Fitness => 'Fitness tréning • Personal training • Kondícia',
            self::Other => 'Iný typ podnikania',
        };
    }
}
