<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Models\Tenant;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
final class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 120);

        return [
            'code' => strtoupper(fake()->bothify('GIFT-####-????')),
            'initial_amount' => $amount,
            'balance_amount' => $amount,
            'currency' => 'EUR',
            'expires_at' => Carbon::instance(fake()->dateTimeBetween('+1 week', '+1 year')),
            'status' => VoucherStatus::Active->value,
            'issued_to_name' => fake()->optional()->name(),
            'issued_to_email' => fake()->optional()->safeEmail(),
            'note' => fake()->optional()->sentence(),
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
            'status' => VoucherStatus::Inactive->value,
        ]);
    }
}
