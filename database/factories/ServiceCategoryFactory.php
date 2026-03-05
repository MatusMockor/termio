<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCategory>
 */
final class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(10, 9999),
            'parent_id' => null,
            'priority' => fake()->numberBetween(0, 100),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
