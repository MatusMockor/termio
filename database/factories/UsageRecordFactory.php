<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
final class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'period' => now()->format('Y-m'),
            'reservations_count' => fake()->numberBetween(0, 100),
            'reservations_limit' => 150,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forPeriod(string $period): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => $period,
        ]);
    }

    public function currentMonth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => now()->format('Y-m'),
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reservations_limit' => -1,
        ]);
    }

    public function atLimit(): static
    {
        return $this->state(function (array $attributes): array {
            $limit = $attributes['reservations_limit'] ?? 150;

            return [
                'reservations_count' => $limit,
            ];
        });
    }

    public function nearLimit(): static
    {
        return $this->state(function (array $attributes): array {
            $limit = $attributes['reservations_limit'] ?? 150;

            return [
                'reservations_count' => (int) ($limit * 0.9),
            ];
        });
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reservations_count' => 0,
        ]);
    }
}
