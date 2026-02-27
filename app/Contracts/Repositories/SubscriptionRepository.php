<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface SubscriptionRepository
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(callable $callback): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subscription;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Subscription $subscription, array $data): Subscription;

    public function findById(int $id): ?Subscription;

    public function findByStripeId(string $stripeId): ?Subscription;

    public function findActiveByTenant(Tenant $tenant): ?Subscription;

    /**
     * @return Collection<int, Subscription>
     */
    public function findByTenant(Tenant $tenant): Collection;

    /**
     * @return Collection<int, Subscription>
     */
    public function getTrialsEndingSoon(int $days): Collection;

    /**
     * @return Collection<int, Subscription>
     */
    public function getScheduledDowngrades(): Collection;

    /**
     * @return Collection<int, Subscription>
     */
    public function getExpiredSubscriptions(): Collection;
}
