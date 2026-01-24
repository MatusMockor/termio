<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionItem>
 */
final class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'stripe_id' => 'si_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_product' => 'prod_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'stripe_price' => 'price_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'quantity' => 1,
        ];
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_id' => $subscription->id,
        ]);
    }
}
