<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Vip = 'vip';
}
