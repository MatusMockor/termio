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
            'timezone' => 'Europe/Bratislava',
            'settings' => [],
            'status' => 'active',
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
}
