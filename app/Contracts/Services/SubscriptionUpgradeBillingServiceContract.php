<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Billing\StripeSubscriptionResultDTO;
use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;

interface SubscriptionUpgradeBillingServiceContract
{
    public function resolvePriceId(Plan $plan, BillingCycle $billingCycle): string;

    public function isFreeSubscription(Subscription $subscription): bool;

    public function createPaidSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
    ): StripeSubscriptionResultDTO;

    public function createTrialSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
        int $trialDays,
    ): StripeSubscriptionResultDTO;

    public function swapPaidSubscription(Subscription $subscription, string $priceId): void;

    public function swapPaidSubscriptionAndInvoice(Subscription $subscription, string $priceId): void;

    public function resumeCanceledPaidSubscription(Subscription $subscription): void;
}
