<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanSlug: string
{
    case Free = 'free';
    case Easy = 'easy';
    case Smart = 'smart';
    case Standard = 'standard';
    case Premium = 'premium';
}
