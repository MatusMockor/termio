<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
final class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Strihanie pánske',
                'Strihanie dámske',
                'Úprava brady',
                'Farbenie',
                'Styling',
                'Masáž hlavy',
            ]).' '.fake()->randomNumber(2),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60, 90]),
            'price' => fake()->randomFloat(2, 10, 100),
            'category' => fake()->randomElement(['Strihanie', 'Úprava', 'Ošetrenie']),
            'is_active' => true,
            'is_bookable_online' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function notBookableOnline(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_bookable_online' => false,
        ]);
    }
}
