<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\PaymentMethodServiceContract;
use App\Contracts\Services\StripeService;
use App\DTOs\Billing\PaymentMethodDTO;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentMethodService implements PaymentMethodServiceContract
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Add a new payment method to tenant and set it as default.
     */
    public function addPaymentMethod(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO
    {
        return $this->storeDefaultPaymentMethod($tenant, $paymentMethodId);
    }

    /**
     * Add a new payment method to tenant without changing default.
     */
    public function addPaymentMethodWithoutDefault(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO
    {
        return $this->storeAdditionalPaymentMethod($tenant, $paymentMethodId);
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
     * Check if card is expiring soon.
     */
    public function isCardExpiringSoon(PaymentMethod $paymentMethod): bool
    {
        if (! $paymentMethod->hasCardExpiration()) {
            return false;
        }

        $expiryDate = Carbon::createFromDate(
            $paymentMethod->card_exp_year,
            $paymentMethod->card_exp_month,
            1
        )->endOfMonth();

        return $expiryDate->diffInDays(now()) <= (int) config('billing.expiring_soon_days', 30);
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

    private function storeDefaultPaymentMethod(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO
    {
        try {
            return DB::transaction(function () use ($tenant, $paymentMethodId): PaymentMethodDTO {
                $stripePaymentMethod = $this->stripeService->attachPaymentMethod($paymentMethodId, (string) $tenant->stripe_id);

                $this->stripeService->setDefaultPaymentMethod((string) $tenant->stripe_id, $paymentMethodId);
                PaymentMethod::where('tenant_id', $tenant->id)->update(['is_default' => false]);

                $paymentMethod = PaymentMethod::create([
                    'tenant_id' => $tenant->id,
                    'stripe_payment_method_id' => $paymentMethodId,
                    'type' => $stripePaymentMethod->type,
                    'card_brand' => $stripePaymentMethod->card?->brand,
                    'card_last4' => $stripePaymentMethod->card?->last4,
                    'card_exp_month' => $stripePaymentMethod->card?->exp_month,
                    'card_exp_year' => $stripePaymentMethod->card?->exp_year,
                    'is_default' => true,
                ]);

                return PaymentMethodDTO::fromModel($paymentMethod);
            });
        } catch (Throwable $exception) {
            $this->detachPaymentMethodSafely($paymentMethodId);

            throw $exception;
        }
    }

    private function storeAdditionalPaymentMethod(Tenant $tenant, string $paymentMethodId): PaymentMethodDTO
    {
        $stripePaymentMethod = $this->stripeService->attachPaymentMethod($paymentMethodId, (string) $tenant->stripe_id);

        try {
            $paymentMethod = PaymentMethod::create([
                'tenant_id' => $tenant->id,
                'stripe_payment_method_id' => $paymentMethodId,
                'type' => $stripePaymentMethod->type,
                'card_brand' => $stripePaymentMethod->card?->brand,
                'card_last4' => $stripePaymentMethod->card?->last4,
                'card_exp_month' => $stripePaymentMethod->card?->exp_month,
                'card_exp_year' => $stripePaymentMethod->card?->exp_year,
                'is_default' => false,
            ]);

            return PaymentMethodDTO::fromModel($paymentMethod);
        } catch (Throwable $exception) {
            $this->detachPaymentMethodSafely($paymentMethodId);

            throw $exception;
        }
    }

    private function detachPaymentMethodSafely(string $paymentMethodId): void
    {
        try {
            $this->stripeService->detachPaymentMethod($paymentMethodId);
        } catch (Throwable) {
            // Best-effort rollback: keep the original exception as the primary failure.
        }
    }
}
