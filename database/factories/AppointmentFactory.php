<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::instance(fake()->dateTimeBetween('now', '+1 week'))
            ->setMinute(fake()->randomElement([0, 30]))
            ->setSecond(0);

        $durationMinutes = fake()->randomElement([30, 45, 60, 90]);

        return [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
            'notes' => fake()->optional()->sentence(),
            'status' => 'pending',
            'source' => 'manual',
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
        ]);
    }

    public function forService(Service $service): static
    {
        return $this->state(function (array $attributes) use ($service): array {
            $startsAt = Carbon::parse($attributes['starts_at']);

            return [
                'service_id' => $service->id,
                'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
            ];
        });
    }

    public function forStaff(User $staff): static
    {
        return $this->state(fn (array $attributes): array => [
            'staff_id' => $staff->id,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'confirmed',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
        ]);
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => 'online',
        ]);
    }

    public function withGoogleEvent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'google_event_id' => fake()->uuid(),
        ]);
    }

    public function at(Carbon $dateTime): static
    {
        return $this->state(function (array $attributes) use ($dateTime): array {
            $duration = Carbon::parse($attributes['starts_at'])
                ->diffInMinutes(Carbon::parse($attributes['ends_at']));

            return [
                'starts_at' => $dateTime,
                'ends_at' => $dateTime->copy()->addMinutes($duration),
            ];
        });
    }
}
