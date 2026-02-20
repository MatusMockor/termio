<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_status' => 'active',
            'stripe_price' => 'price_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan_id' => $plan->id,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'billing_cycle' => 'monthly',
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'billing_cycle' => 'yearly',
        ]);
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_status' => 'canceled',
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function withoutEndsAt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ends_at' => null,
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_status' => 'past_due',
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);
    }

    public function withScheduledDowngrade(Plan $plan): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_plan_id' => $plan->id,
            'scheduled_change_at' => now()->addMonth(),
        ]);
    }
}
