<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\StripeService;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PaymentMethodService
{
    private const int EXPIRING_SOON_DAYS = 30;

    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Add a new payment method to tenant.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function addPaymentMethod(Tenant $tenant, string $paymentMethodId, bool $setDefault = true): PaymentMethod
    {
        // Attach to Stripe customer
        $stripePaymentMethod = $this->stripeService->attachPaymentMethod($paymentMethodId, (string) $tenant->stripe_id);

        return DB::transaction(function () use ($tenant, $paymentMethodId, $stripePaymentMethod, $setDefault): PaymentMethod {
            // If setting as default, update Stripe and unset other defaults
            if ($setDefault) {
                $this->stripeService->setDefaultPaymentMethod((string) $tenant->stripe_id, $paymentMethodId);
                PaymentMethod::where('tenant_id', $tenant->id)->update(['is_default' => false]);
            }

            // Create local record
            return PaymentMethod::create([
                'tenant_id' => $tenant->id,
                'stripe_payment_method_id' => $paymentMethodId,
                'type' => $stripePaymentMethod->type,
                'card_brand' => $stripePaymentMethod->card?->brand,
                'card_last4' => $stripePaymentMethod->card?->last4,
                'card_exp_month' => $stripePaymentMethod->card?->exp_month,
                'card_exp_year' => $stripePaymentMethod->card?->exp_year,
                'is_default' => $setDefault,
            ]);
        });
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): void
    {
        // Detach from Stripe
        $this->stripeService->detachPaymentMethod($paymentMethod->stripe_payment_method_id);

        // Delete local record
        $paymentMethod->delete();
    }

    /**
     * Set a payment method as default.
     */
    public function setDefaultPaymentMethod(Tenant $tenant, PaymentMethod $paymentMethod): void
    {
        DB::transaction(function () use ($tenant, $paymentMethod): void {
            // Update Stripe
            $this->stripeService->setDefaultPaymentMethod(
                (string) $tenant->stripe_id,
                $paymentMethod->stripe_payment_method_id
            );

            // Update local records
            PaymentMethod::where('tenant_id', $tenant->id)->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);
        });
    }

    /**
     * Get all payment methods for tenant.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function getPaymentMethods(Tenant $tenant): Collection
    {
        return PaymentMethod::where('tenant_id', $tenant->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Check if card is expiring soon (within 30 days).
     */
    public function isCardExpiringSoon(PaymentMethod $paymentMethod): bool
    {
        if ($paymentMethod->card_exp_month === null || $paymentMethod->card_exp_year === null) {
            return false;
        }

        $expiryDate = Carbon::createFromDate(
            $paymentMethod->card_exp_year,
            $paymentMethod->card_exp_month,
            1
        )->endOfMonth();

        return $expiryDate->diffInDays(now()) <= self::EXPIRING_SOON_DAYS;
    }

    /**
     * Get default payment method for tenant.
     */
    public function getDefaultPaymentMethod(Tenant $tenant): ?PaymentMethod
    {
        return PaymentMethod::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->first();
    }
}
