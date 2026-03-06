<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface StripeCustomerProvisionerContract
{
    public function ensureCustomerId(Tenant $tenant): string;
}
