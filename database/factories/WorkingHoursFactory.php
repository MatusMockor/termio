<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\WorkingHours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHours>
 */
final class WorkingHoursFactory extends Factory
{
    protected $model = WorkingHours::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
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

    public function forDay(int $dayOfWeek): static
    {
        return $this->state(fn (array $attributes): array => [
            'day_of_week' => $dayOfWeek,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function hours(string $start, string $end): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
