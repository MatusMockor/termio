<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingFieldType;
use App\Models\BookingField;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingField>
 */
final class BookingFieldFactory extends Factory
{
    protected $model = BookingField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->words(2, true);
        $key = Str::snake($label).'_'.fake()->numberBetween(10, 9999);
        $type = fake()->randomElement(BookingFieldType::values());

        return [
            'key' => $key,
            'label' => ucfirst($label),
            'type' => $type,
            'options' => $type === BookingFieldType::Select->value ? ['Option A', 'Option B'] : null,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
