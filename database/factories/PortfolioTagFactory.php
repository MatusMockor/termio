<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PortfolioTag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortfolioTag>
 */
final class PortfolioTagFactory extends Factory
{
    protected $model = PortfolioTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Strihanie',
            'Farbenie',
            'Svadobné účesy',
            'Pánske strihy',
            'Dámske strihy',
            'Balayage',
            'Ombré',
            'Melír',
        ]).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => fake()->optional()->hexColor(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes): array => [
            'color' => $color,
        ]);
    }
}
