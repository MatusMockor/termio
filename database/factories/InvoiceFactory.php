<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountNet = fake()->randomFloat(2, 5, 50);
        $vatRate = 20.00;
        $vatAmount = $amountNet * ($vatRate / 100);
        $amountGross = $amountNet + $vatAmount;

        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => null,
            'stripe_invoice_id' => 'in_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'invoice_number' => 'INV-'.now()->format('Y-m').'-'.fake()->unique()->numerify('####'),
            'amount_net' => $amountNet,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount_gross' => $amountGross,
            'currency' => 'EUR',
            'customer_name' => fake()->company(),
            'customer_address' => fake()->address(),
            'customer_country' => fake()->randomElement(['SK', 'CZ', 'AT', 'DE']),
            'customer_vat_id' => fake()->optional()->regexify('SK[0-9]{10}'),
            'line_items' => [
                [
                    'description' => 'SMART Plan - Monthly Subscription',
                    'quantity' => 1,
                    'unit_price' => $amountNet,
                    'amount' => $amountNet,
                ],
            ],
            'status' => 'paid',
            'paid_at' => now(),
            'pdf_path' => null,
            'notes' => null,
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
            'customer_name' => $tenant->name,
        ]);
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscription_id' => $subscription->id,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'paid_at' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'open',
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'void',
            'paid_at' => null,
        ]);
    }

    public function withoutVat(): static
    {
        return $this->state(function (array $attributes): array {
            $amountNet = $attributes['amount_net'];

            return [
                'vat_rate' => 0.00,
                'vat_amount' => 0.00,
                'amount_gross' => $amountNet,
            ];
        });
    }
}
