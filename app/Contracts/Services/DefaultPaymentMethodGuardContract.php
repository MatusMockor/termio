<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface DefaultPaymentMethodGuardContract
{
    /**
     * Returns true when default payment method is verified, false when explicitly absent,
     * null when verification is inconclusive (e.g. transient Stripe/API issue).
     */
    public function determineLiveDefaultPaymentMethod(Tenant $tenant): ?bool;

    public function hasLiveDefaultPaymentMethod(Tenant $tenant): bool;

    public function ensureLiveDefaultPaymentMethod(Tenant $tenant): string;
}
