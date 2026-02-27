<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;

interface SubscriptionUpgradeBillingServiceContract
{
    public function resolvePriceId(Plan $plan, BillingCycle $billingCycle): string;

    public function isFreeSubscription(Subscription $subscription): bool;

    /**
     * @return object{id: string, status: string}
     */
    public function createPaidSubscriptionFromFree(
        Subscription $subscription,
        string $priceId,
    ): object;

    public function swapPaidSubscription(Subscription $subscription, string $priceId): void;

    public function swapPaidSubscriptionAndInvoice(Subscription $subscription, string $priceId): void;

    public function resumeCanceledPaidSubscription(Subscription $subscription): void;
}
