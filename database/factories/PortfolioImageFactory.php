<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PortfolioImage;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioImage>
 */
final class PortfolioImageFactory extends Factory
{
    protected $model = PortfolioImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->optional()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'file_path' => 'portfolios/'.fake()->numberBetween(1, 100).'/staff/'.fake()->numberBetween(1, 10).'/'.fake()->uuid().'.jpg',
            'file_name' => fake()->uuid().'.jpg',
            'file_size' => fake()->numberBetween(100000, 5000000),
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'sort_order' => fake()->numberBetween(0, 10),
            'is_public' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forStaff(StaffProfile $staff): static
    {
        return $this->state(fn (array $attributes): array => [
            'staff_id' => $staff->id,
            'tenant_id' => $staff->tenant_id,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => $title,
        ]);
    }
}
