<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\DefaultPaymentMethodGuardContract;
use App\Enums\SubscriptionStatus;
use App\Jobs\Subscription\ProcessExpiredTrialsJob;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProcessExpiredTrialsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_trial_without_live_default_payment_method_downgrades_to_free(): void
    {
        $this->createTenantWithOwner();
        [$freePlan, $paidPlan] = $this->createPlans();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($paidPlan)
            ->create([
                'stripe_status' => SubscriptionStatus::Trialing->value,
                'stripe_id' => 'sub_trial_without_pm_'.$this->tenant->id,
                'trial_ends_at' => now()->subMinute(),
                'billing_cycle' => 'monthly',
            ]);

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('determineLiveDefaultPaymentMethod')
            ->willReturn(false);

        $job = new ProcessExpiredTrialsJob(
            app(SubscriptionRepository::class),
            app(PlanRepository::class),
            $paymentMethodGuard,
        );

        $job->handle();

        $subscription->refresh();
        $this->assertSame($freePlan->id, $subscription->plan_id);
        $this->assertSame('free_'.$this->tenant->id, $subscription->stripe_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->stripe_status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_expired_trial_with_live_default_payment_method_converts_to_active(): void
    {
        $this->createTenantWithOwner();
        [, $paidPlan] = $this->createPlans();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($paidPlan)
            ->create([
                'stripe_status' => SubscriptionStatus::Trialing->value,
                'stripe_id' => 'sub_trial_with_pm_'.$this->tenant->id,
                'trial_ends_at' => now()->subMinute(),
                'billing_cycle' => 'monthly',
            ]);

        $paymentMethodGuard = $this->createMock(DefaultPaymentMethodGuardContract::class);
        $paymentMethodGuard->expects($this->once())
            ->method('determineLiveDefaultPaymentMethod')
            ->willReturn(true);

        $job = new ProcessExpiredTrialsJob(
            app(SubscriptionRepository::class),
            app(PlanRepository::class),
            $paymentMethodGuard,
        );

        $job->handle();

        $subscription->refresh();
        $this->assertSame($paidPlan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->stripe_status);
        $this->assertNull($subscription->trial_ends_at);
    }

    /**
     * @return array{Plan, Plan}
     */
    private function createPlans(): array
    {
        $freePlan = Plan::factory()->create([
            'slug' => 'free',
            'sort_order' => 0,
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
        ]);

        $paidPlan = Plan::factory()->create([
            'slug' => 'easy',
            'sort_order' => 1,
            'monthly_price' => 6.00,
            'yearly_price' => 54.00,
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);

        return [$freePlan, $paidPlan];
    }
}
