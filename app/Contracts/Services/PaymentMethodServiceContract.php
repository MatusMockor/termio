<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Billing\PaymentMethodDTO;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface PaymentMethodServiceContract
{
    /**
     * Add a new payment method to tenant and set it as default.
     */
    public function addPaymentMethod(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO;

    /**
     * Add a new payment method to tenant without changing default.
     */
    public function addPaymentMethodWithoutDefault(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO;

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): void;

    /**
     * Set a payment method as default.
     */
    public function setDefaultPaymentMethod(Tenant $tenant, PaymentMethod $paymentMethod): void;

    /**
     * Get all payment methods for tenant.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function getPaymentMethods(Tenant $tenant): Collection;

    /**
     * Check if card is expiring soon.
     */
    public function isCardExpiringSoon(PaymentMethod $paymentMethod): bool;

    /**
     * Get default payment method for tenant.
     */
    public function getDefaultPaymentMethod(Tenant $tenant): ?PaymentMethod;
}
