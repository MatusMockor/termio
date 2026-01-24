<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'business_type' => fake()->randomElement(['barbershop', 'salon', 'spa', 'clinic']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'country' => fake()->randomElement(['SK', 'CZ', 'AT', 'DE', 'PL']),
            'vat_id' => null,
            'vat_id_verified_at' => null,
            'timezone' => 'Europe/Bratislava',
            'settings' => [],
            'status' => 'active',
            'stripe_id' => null,
            'pm_type' => null,
            'pm_last_four' => null,
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'suspended',
        ]);
    }

    public function withStripe(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }

    public function withPaymentMethod(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'pm_type' => 'card',
            'pm_last_four' => fake()->numerify('####'),
        ]);
    }

    public function withVatId(): static
    {
        return $this->state(fn (array $attributes): array => [
            'country' => 'SK',
            'vat_id' => 'SK'.fake()->numerify('##########'),
            'vat_id_verified_at' => now(),
        ]);
    }
}
