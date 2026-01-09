<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
final class TimeOffFactory extends Factory
{
    protected $model = TimeOff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'staff_id' => null,
            'date' => fake()->dateTimeBetween('tomorrow', '+1 month'),
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(),
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
            'tenant_id' => $staff->tenant_id,
            'staff_id' => $staff->id,
        ]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date' => $date,
        ]);
    }

    public function allDay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function partial(string $startTime, string $endTime): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function withReason(string $reason): static
    {
        return $this->state(fn (array $attributes): array => [
            'reason' => $reason,
        ]);
    }
}
