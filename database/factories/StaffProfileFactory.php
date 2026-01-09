<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
final class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'display_name' => fake()->name(),
            'bio' => fake()->optional()->sentence(),
            'photo_url' => fake()->optional()->imageUrl(200, 200, 'people'),
            'specializations' => fake()->randomElements(
                ['Fade', 'Beard trim', 'Classic cut', 'Coloring', 'Styling'],
                fake()->numberBetween(1, 3)
            ),
            'is_bookable' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function bookable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_bookable' => true,
        ]);
    }

    public function notBookable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_bookable' => false,
        ]);
    }
}
