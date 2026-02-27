<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanSlug;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['FREE', 'EASY', 'SMART', 'STANDARD', 'PREMIUM']);

        return [
            'name' => $name,
            'slug' => strtolower($name),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 0, 50),
            'yearly_price' => fake()->randomFloat(2, 0, 450),
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'features' => $this->defaultFeatures(),
            'limits' => $this->defaultLimits(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'is_public' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'FREE',
            'slug' => PlanSlug::Free->value,
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
            'limits' => [
                'reservations_per_month' => 150,
                'users' => 1,
                'locations' => 1,
                'services' => 10,
                'sms_credits_per_month' => 0,
            ],
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'PREMIUM',
            'slug' => PlanSlug::Premium->value,
            'monthly_price' => 49.90,
            'yearly_price' => 449.00,
            'features' => array_merge($this->defaultFeatures(), [
                'white_label' => true,
                'sms_reminders' => true,
                'api_access' => true,
            ]),
            'limits' => [
                'reservations_per_month' => -1,
                'users' => -1,
                'locations' => -1,
                'services' => -1,
                'sms_credits_per_month' => -1,
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFeatures(): array
    {
        return [
            'online_booking_widget' => true,
            'manual_reservations' => true,
            'calendar_view' => 'basic',
            'client_database' => 'basic',
            'email_confirmations' => true,
            'email_reminders' => true,
            'sms_reminders' => false,
            'custom_logo' => false,
            'custom_colors' => false,
            'custom_booking_url' => false,
            'custom_domain' => false,
            'white_label' => false,
            'google_calendar_sync' => false,
            'payment_gateway' => false,
            'api_access' => false,
            'zapier_integration' => false,
            'multi_language' => false,
            'staff_permissions' => false,
            'client_segmentation' => false,
            'waitlist_management' => false,
            'recurring_appointments' => false,
            'gift_vouchers' => false,
            'reports_statistics' => 'basic',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function defaultLimits(): array
    {
        return [
            'reservations_per_month' => 150,
            'users' => 1,
            'locations' => 1,
            'services' => 10,
            'sms_credits_per_month' => 0,
        ];
    }
}
