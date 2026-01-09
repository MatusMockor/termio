<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
            'total_visits' => fake()->numberBetween(0, 50),
            'total_spent' => fake()->randomFloat(2, 0, 1000),
            'last_visit_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'status' => 'active',
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'vip',
            'total_visits' => fake()->numberBetween(20, 100),
            'total_spent' => fake()->randomFloat(2, 500, 5000),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
