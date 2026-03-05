<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WaitlistEntrySource;
use App\Enums\WaitlistEntryStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
final class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preferred_staff_id' => null,
            'preferred_date' => Carbon::instance(fake()->dateTimeBetween('tomorrow', '+30 days'))->toDateString(),
            'time_from' => '09:00:00',
            'time_to' => '17:00:00',
            'client_name' => fake()->name(),
            'client_phone' => fake()->numerify('+4219########'),
            'client_email' => fake()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
            'status' => WaitlistEntryStatus::Pending->value,
            'source' => WaitlistEntrySource::Owner->value,
            'converted_appointment_id' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
            'service_id' => Service::factory()->forTenant($tenant),
        ]);
    }
}
