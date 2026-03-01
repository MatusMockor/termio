<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;

class BillingPaymentMethodsListAction
{
    /**
     * @return array<int, array{
     *     id: int,
     *     stripe_payment_method_id: string,
     *     type: string,
     *     card_brand: string,
     *     card_last4: string,
     *     card_exp_month: int,
     *     card_exp_year: int,
     *     is_default: bool
     * }>
     */
    public function handle(Tenant $tenant): array
    {
        try {
            $defaultPaymentMethod = $tenant->getDefaultPaymentMethod();

            if ($defaultPaymentMethod === null) {
                return [];
            }

            return [$this->mapDefaultPaymentMethod($defaultPaymentMethod)];
        } catch (\Throwable $exception) {
            Log::warning('Unable to fetch default payment method from Stripe.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array{
     *     id: int,
     *     stripe_payment_method_id: string,
     *     type: string,
     *     card_brand: string,
     *     card_last4: string,
     *     card_exp_month: int,
     *     card_exp_year: int,
     *     is_default: bool
     * }
     */
    private function mapDefaultPaymentMethod(CashierPaymentMethod $paymentMethod): array
    {
        $stripePaymentMethod = $paymentMethod->asStripePaymentMethod();
        $card = $stripePaymentMethod->card;

        if ($card === null) {
            return [
                'id' => 1,
                'stripe_payment_method_id' => (string) $stripePaymentMethod->id,
                'type' => (string) $stripePaymentMethod->type,
                'card_brand' => '',
                'card_last4' => '',
                'card_exp_month' => 0,
                'card_exp_year' => 0,
                'is_default' => true,
            ];
        }

        return [
            'id' => 1,
            'stripe_payment_method_id' => (string) $stripePaymentMethod->id,
            'type' => (string) $stripePaymentMethod->type,
            'card_brand' => (string) $card->brand,
            'card_last4' => (string) $card->last4,
            'card_exp_month' => (int) $card->exp_month,
            'card_exp_year' => (int) $card->exp_year,
            'is_default' => true,
        ];
    }
}
