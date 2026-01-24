<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
final class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stripe_payment_method_id' => 'pm_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'type' => 'card',
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'card_last4' => fake()->numerify('####'),
            'card_exp_month' => fake()->numberBetween(1, 12),
            'card_exp_year' => fake()->numberBetween(now()->year + 1, now()->year + 5),
            'is_default' => false,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    public function visa(): static
    {
        return $this->state(fn (array $attributes): array => [
            'card_brand' => 'visa',
        ]);
    }

    public function mastercard(): static
    {
        return $this->state(fn (array $attributes): array => [
            'card_brand' => 'mastercard',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'card_exp_month' => 1,
            'card_exp_year' => now()->year - 1,
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes): array => [
            'card_exp_month' => now()->addMonth()->month,
            'card_exp_year' => now()->addMonth()->year,
        ]);
    }

    public function sepaDebit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'sepa_debit',
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ]);
    }
}
