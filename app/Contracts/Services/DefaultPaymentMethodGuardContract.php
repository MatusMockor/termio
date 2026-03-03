<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface DefaultPaymentMethodGuardContract
{
    public function hasLiveDefaultPaymentMethod(Tenant $tenant): bool;

    public function ensureLiveDefaultPaymentMethod(Tenant $tenant): string;
}
