<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function create(array $data): Subscription
    {
        return Subscription::create($data);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh() ?? $subscription;
    }

    public function findById(int $id): ?Subscription
    {
        return Subscription::find($id);
    }

    public function findByStripeId(string $stripeId): ?Subscription
    {
        return Subscription::where('stripe_id', $stripeId)->first();
    }

    public function findActiveByTenant(Tenant $tenant): ?Subscription
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->whereIn('stripe_status', SubscriptionStatus::activeStatusValues())
            ->first();
    }

    public function findByTenant(Tenant $tenant): Collection
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTrialsEndingSoon(int $days): Collection
    {
        return Subscription::where('stripe_status', SubscriptionStatus::Trialing)
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->get();
    }

    public function getScheduledDowngrades(): Collection
    {
        return Subscription::whereNotNull('scheduled_plan_id')
            ->where('scheduled_change_at', '<=', now())
            ->get();
    }

    public function getExpiredSubscriptions(): Collection
    {
        return Subscription::whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->where('stripe_status', '!=', SubscriptionStatus::Canceled)
            ->get();
    }
}
