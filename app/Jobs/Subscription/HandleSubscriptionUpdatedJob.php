<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class HandleSubscriptionUpdatedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $stripeData
     */
    public function __construct(
        private readonly int $subscriptionId,
        private readonly string $status,
        private readonly array $stripeData,
    ) {}

    public function handle(SubscriptionRepository $subscriptions): void
    {
        $subscription = $subscriptions->findById($this->subscriptionId);

        if ($subscription === null) {
            Log::warning('HandleSubscriptionUpdatedJob: subscription not found', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        /** @var array<string, mixed> $updateData */
        $updateData = [
            'stripe_status' => $this->status,
        ];

        // Update trial end date if present
        if (isset($this->stripeData['trial_end']) && is_int($this->stripeData['trial_end'])) {
            $updateData['trial_ends_at'] = Carbon::createFromTimestamp($this->stripeData['trial_end']);
        }

        // Update cancel date if present
        if (isset($this->stripeData['cancel_at']) && is_int($this->stripeData['cancel_at'])) {
            $updateData['ends_at'] = Carbon::createFromTimestamp($this->stripeData['cancel_at']);
        } elseif ($this->shouldSetEndsAtFromPeriodEnd()) {
            /** @var int $periodEnd */
            $periodEnd = $this->stripeData['current_period_end'];
            $updateData['ends_at'] = Carbon::createFromTimestamp($periodEnd);
        }

        // Update current price if changed
        $priceId = $this->extractPriceId();

        if ($priceId !== null) {
            $updateData['stripe_price'] = $priceId;
        }

        $subscriptions->update($subscription, $updateData);

        Log::info('HandleSubscriptionUpdatedJob: subscription updated', [
            'subscription_id' => $this->subscriptionId,
            'status' => $this->status,
        ]);
    }

    private function shouldSetEndsAtFromPeriodEnd(): bool
    {
        $canceledAt = $this->stripeData['canceled_at'] ?? null;
        $cancelAtPeriodEnd = $this->stripeData['cancel_at_period_end'] ?? false;
        $currentPeriodEnd = $this->stripeData['current_period_end'] ?? null;

        return $canceledAt !== null
            && $cancelAtPeriodEnd === true
            && is_int($currentPeriodEnd);
    }

    private function extractPriceId(): ?string
    {
        $items = $this->stripeData['items'] ?? null;

        if ($items === null) {
            return null;
        }

        /** @var array<string, mixed> $itemsData */
        $itemsData = $items;
        $data = $itemsData['data'] ?? null;

        if (! is_array($data) || ! isset($data[0])) {
            return null;
        }

        /** @var array<string, mixed> $firstItem */
        $firstItem = $data[0];
        $price = $firstItem['price'] ?? null;

        if (! is_array($price)) {
            return null;
        }

        /** @var array<string, mixed> $priceData */
        $priceData = $price;

        return isset($priceData['id']) && is_string($priceData['id'])
            ? $priceData['id']
            : null;
    }
}
