<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Exceptions\SubscriptionException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DefaultPaymentMethodGuardService implements DefaultPaymentMethodGuardContract
{
    public function hasLiveDefaultPaymentMethod(Tenant $tenant): bool
    {
        if (! $tenant->hasStripeId()) {
            return false;
        }

        try {
            return $this->resolveDefaultPaymentMethodId($tenant) !== null;
        } catch (Throwable $exception) {
            Log::warning('Unable to verify live default payment method.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function ensureLiveDefaultPaymentMethod(Tenant $tenant): string
    {
        if (! $tenant->hasStripeId()) {
            throw SubscriptionException::paymentMethodRequired();
        }

        try {
            $defaultPaymentMethodId = $this->resolveDefaultPaymentMethodId($tenant);
        } catch (Throwable $exception) {
            throw SubscriptionException::stripeError($exception->getMessage());
        }

        if (! $defaultPaymentMethodId) {
            throw SubscriptionException::paymentMethodRequired();
        }

        return $defaultPaymentMethodId;
    }

    private function resolveDefaultPaymentMethodId(Tenant $tenant): ?string
    {
        if (! $tenant->hasStripeId()) {
            return null;
        }

        $stripeCustomer = $tenant->asStripeCustomer();
        $defaultPaymentMethod = $stripeCustomer->invoice_settings?->default_payment_method;

        if (is_string($defaultPaymentMethod) && $defaultPaymentMethod !== '') {
            return $defaultPaymentMethod;
        }

        $defaultPaymentMethodId = $defaultPaymentMethod->id ?? null;

        if (! is_string($defaultPaymentMethodId) || $defaultPaymentMethodId === '') {
            return null;
        }

        return $defaultPaymentMethodId;
    }
}
