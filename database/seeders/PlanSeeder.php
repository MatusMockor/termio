<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanSlug;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use RuntimeException;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'FREE',
                'slug' => PlanSlug::Free->value,
                'description' => 'Perfect for trying out Termio',
                'monthly_price' => 0.00,
                'yearly_price' => 0.00,
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'sort_order' => 0,
                'features' => [
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
                ],
                'limits' => [
                    'reservations_per_month' => 150,
                    'users' => 1,
                    'locations' => 1,
                    'services' => 10,
                    'sms_credits_per_month' => 0,
                ],
            ],
            [
                'name' => 'EASY',
                'slug' => PlanSlug::Easy->value,
                'description' => 'For solo practitioners getting started',
                'monthly_price' => 6.00,
                'yearly_price' => 54.00,
                'stripe_monthly_price_id' => config('cashier.prices.easy.monthly'),
                'stripe_yearly_price_id' => config('cashier.prices.easy.yearly'),
                'sort_order' => 1,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'basic',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => false,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => false,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => false,
                    'zapier_integration' => false,
                    'multi_language' => true,
                    'staff_permissions' => false,
                    'client_segmentation' => false,
                    'waitlist_management' => false,
                    'recurring_appointments' => true,
                    'gift_vouchers' => false,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => 350,
                    'users' => 1,
                    'locations' => 1,
                    'services' => -1,
                    'sms_credits_per_month' => 0,
                ],
            ],
            [
                'name' => 'SMART',
                'slug' => PlanSlug::Smart->value,
                'description' => 'Best value for growing businesses',
                'monthly_price' => 15.00,
                'yearly_price' => 135.00,
                'stripe_monthly_price_id' => config('cashier.prices.smart.monthly'),
                'stripe_yearly_price_id' => config('cashier.prices.smart.yearly'),
                'sort_order' => 2,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => 1500,
                    'users' => 3,
                    'locations' => 2,
                    'services' => -1,
                    'sms_credits_per_month' => 50,
                ],
            ],
            [
                'name' => 'STANDARD',
                'slug' => PlanSlug::Standard->value,
                'description' => 'For established businesses with teams',
                'monthly_price' => 30.00,
                'yearly_price' => 270.00,
                'stripe_monthly_price_id' => config('cashier.prices.standard.monthly'),
                'stripe_yearly_price_id' => config('cashier.prices.standard.yearly'),
                'sort_order' => 3,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => -1,
                    'users' => 10,
                    'locations' => 10,
                    'services' => -1,
                    'sms_credits_per_month' => 200,
                ],
            ],
            [
                'name' => 'PREMIUM',
                'slug' => PlanSlug::Premium->value,
                'description' => 'Enterprise features with priority support',
                'monthly_price' => 50.00,
                'yearly_price' => 450.00,
                'stripe_monthly_price_id' => config('cashier.prices.premium.monthly'),
                'stripe_yearly_price_id' => config('cashier.prices.premium.yearly'),
                'sort_order' => 4,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => true,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => -1,
                    'users' => -1,
                    'locations' => -1,
                    'services' => -1,
                    'sms_credits_per_month' => -1,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $this->assertStripePriceIdsConfigured($planData);

            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }

    /**
     * @param  array{name: string, slug: string, stripe_monthly_price_id: string|null, stripe_yearly_price_id: string|null}  $planData
     */
    private function assertStripePriceIdsConfigured(array $planData): void
    {
        if ($planData['slug'] === PlanSlug::Free->value) {
            return;
        }

        if ($planData['stripe_monthly_price_id'] && $planData['stripe_yearly_price_id']) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Missing Stripe price IDs for paid plan "%s" (%s). Configure STRIPE_PRICE_%s_MONTHLY and STRIPE_PRICE_%s_YEARLY.',
                $planData['name'],
                $planData['slug'],
                strtoupper($planData['slug']),
                strtoupper($planData['slug']),
            ),
        );
    }
}
