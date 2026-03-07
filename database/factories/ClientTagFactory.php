<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClientTag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientTag>
 */
final class ClientTagFactory extends Factory
{
    protected $model = ClientTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
