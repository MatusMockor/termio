<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Subscription;

use App\Actions\Subscription\SubscriptionImmediateUpgradeAction;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\Contracts\Services\UsageValidationServiceContract;
use App\DTOs\Subscription\ImmediateUpgradeSubscriptionDTO;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Repositories\Eloquent\EloquentPlanRepository;
use App\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Services\Validation\ValidationChainBuilder;
use App\Services\Validation\Validators\CanDowngradeValidator;
use App\Services\Validation\Validators\CanUpgradeValidator;
use App\Services\Validation\Validators\PlanExistsValidator;
use App\Services\Validation\Validators\SubscriptionExistsValidator;
use App\Services\Validation\Validators\UsageLimitsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class SubscriptionImmediateUpgradeActionTest extends TestCase
{
    use RefreshDatabase;

    private Plan $freePlan;

    private Plan $easyPlan;

    private Plan $smartPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithOwner();

        $this->freePlan = Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'sort_order' => 0,
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);
        $this->easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'sort_order' => 1,
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
        ]);
        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'sort_order' => 2,
            'stripe_monthly_price_id' => 'price_smart_monthly',
            'stripe_yearly_price_id' => 'price_smart_yearly',
        ]);
    }

    public function test_paid_non_trial_upgrade_uses_swap_and_invoice(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->easyPlan)
            ->create([
                'stripe_id' => 'sub_paid_001',
                'stripe_status' => SubscriptionStatus::Active->value,
                'billing_cycle' => 'monthly',
                'trial_ends_at' => null,
                'ends_at' => null,
            ]);

        $action = $this->buildAction(
            $this->createUpgradeValidationChainBuilder(),
            $this->mockBillingService(function (SubscriptionUpgradeBillingServiceContract $billingService) use ($subscription): void {
                $billingService->expects($this->once())
                    ->method('resolvePriceId')
                    ->with(
                        $this->callback(fn (Plan $plan): bool => $plan->is($this->smartPlan)),
                        'monthly',
                    )
                    ->willReturn('price_smart_monthly');
                $billingService->expects($this->once())
                    ->method('isFreeSubscription')
                    ->with($this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id))
                    ->willReturn(false);
                $billingService->expects($this->once())
                    ->method('resumeCanceledPaidSubscription')
                    ->with($this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id));
                $billingService->expects($this->once())
                    ->method('swapPaidSubscriptionAndInvoice')
                    ->with(
                        $this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id),
                        'price_smart_monthly',
                    );
                $billingService->expects($this->never())
                    ->method('swapPaidSubscription');
            }),
        );

        $result = $action->handle(new ImmediateUpgradeSubscriptionDTO(
            subscriptionId: $subscription->id,
            newPlanId: $this->smartPlan->id,
            billingCycle: 'monthly',
        ));

        $this->assertSame($this->smartPlan->id, $result->plan_id);
        $this->assertSame('price_smart_monthly', $result->stripe_price);
        $this->assertSame('monthly', $result->billing_cycle);
        $this->assertNull($result->ends_at);
        $this->assertNull($result->scheduled_plan_id);
        $this->assertNull($result->scheduled_change_at);
    }

    public function test_trial_upgrade_uses_swap_without_invoice_and_keeps_trial_end_date(): void
    {
        Notification::fake();

        $trialEndsAt = now()->addDays(7);

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->easyPlan)
            ->create([
                'stripe_id' => 'sub_trial_001',
                'stripe_status' => SubscriptionStatus::Trialing->value,
                'billing_cycle' => 'monthly',
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => null,
                'scheduled_plan_id' => $this->freePlan->id,
                'scheduled_change_at' => now()->addDay(),
            ]);

        $action = $this->buildAction(
            $this->createUpgradeValidationChainBuilder(),
            $this->mockBillingService(function (SubscriptionUpgradeBillingServiceContract $billingService) use ($subscription): void {
                $billingService->expects($this->once())
                    ->method('resolvePriceId')
                    ->with(
                        $this->callback(fn (Plan $plan): bool => $plan->is($this->smartPlan)),
                        'monthly',
                    )
                    ->willReturn('price_smart_monthly');
                $billingService->expects($this->once())
                    ->method('isFreeSubscription')
                    ->with($this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id))
                    ->willReturn(false);
                $billingService->expects($this->once())
                    ->method('resumeCanceledPaidSubscription')
                    ->with($this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id));
                $billingService->expects($this->once())
                    ->method('swapPaidSubscription')
                    ->with(
                        $this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id),
                        'price_smart_monthly',
                    );
                $billingService->expects($this->never())
                    ->method('swapPaidSubscriptionAndInvoice');
            }),
        );

        $result = $action->handle(new ImmediateUpgradeSubscriptionDTO(
            subscriptionId: $subscription->id,
            newPlanId: $this->smartPlan->id,
            billingCycle: 'monthly',
        ));

        $this->assertSame($this->smartPlan->id, $result->plan_id);
        $this->assertSame($trialEndsAt->toDateTimeString(), $result->trial_ends_at?->toDateTimeString());
        $this->assertNull($result->ends_at);
        $this->assertNull($result->scheduled_plan_id);
        $this->assertNull($result->scheduled_change_at);
    }

    public function test_free_subscription_upgrade_uses_create_paid_subscription_flow(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->freePlan)
            ->create([
                'stripe_id' => 'free_'.$this->tenant->id,
                'stripe_status' => SubscriptionStatus::Active->value,
                'billing_cycle' => 'monthly',
                'ends_at' => now()->addDay(),
                'scheduled_plan_id' => $this->easyPlan->id,
                'scheduled_change_at' => now()->addDay(),
            ]);

        $stripeSubscription = (object) [
            'id' => 'sub_new_paid_123',
            'status' => SubscriptionStatus::Active->value,
        ];

        $action = $this->buildAction(
            $this->createUpgradeValidationChainBuilder(),
            $this->mockBillingService(function (SubscriptionUpgradeBillingServiceContract $billingService) use ($subscription, $stripeSubscription): void {
                $billingService->expects($this->once())
                    ->method('resolvePriceId')
                    ->with(
                        $this->callback(fn (Plan $plan): bool => $plan->is($this->easyPlan)),
                        'yearly',
                    )
                    ->willReturn('price_easy_yearly');
                $billingService->expects($this->once())
                    ->method('isFreeSubscription')
                    ->with($this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id))
                    ->willReturn(true);
                $billingService->expects($this->once())
                    ->method('createPaidSubscriptionFromFree')
                    ->with(
                        $this->callback(fn (Subscription $candidate): bool => $candidate->id === $subscription->id),
                        'price_easy_yearly',
                    )
                    ->willReturn($stripeSubscription);
                $billingService->expects($this->never())
                    ->method('resumeCanceledPaidSubscription');
                $billingService->expects($this->never())
                    ->method('swapPaidSubscription');
                $billingService->expects($this->never())
                    ->method('swapPaidSubscriptionAndInvoice');
            }),
        );

        $result = $action->handle(new ImmediateUpgradeSubscriptionDTO(
            subscriptionId: $subscription->id,
            newPlanId: $this->easyPlan->id,
            billingCycle: 'yearly',
        ));

        $this->assertSame($this->easyPlan->id, $result->plan_id);
        $this->assertSame('sub_new_paid_123', $result->stripe_id);
        $this->assertSame(SubscriptionStatus::Active->value, $result->stripe_status->value);
        $this->assertSame('price_easy_yearly', $result->stripe_price);
        $this->assertSame('yearly', $result->billing_cycle);
        $this->assertNull($result->ends_at);
        $this->assertNull($result->scheduled_plan_id);
        $this->assertNull($result->scheduled_change_at);
    }

    private function buildAction(
        ValidationChainBuilder $validationChainBuilder,
        SubscriptionUpgradeBillingServiceContract $billingService,
    ): SubscriptionImmediateUpgradeAction {
        return new SubscriptionImmediateUpgradeAction(
            new EloquentSubscriptionRepository,
            new EloquentPlanRepository,
            $validationChainBuilder,
            $billingService,
        );
    }

    private function createUpgradeValidationChainBuilder(): ValidationChainBuilder
    {
        $subscriptionService = $this->createMock(SubscriptionServiceContract::class);
        $subscriptionService->method('canUpgradeTo')->willReturn(true);
        $subscriptionService->method('canDowngradeTo')->willReturn(true);

        $usageValidationService = $this->createMock(UsageValidationServiceContract::class);
        $usageValidationService->method('checkLimitViolations')->willReturn([]);

        return new ValidationChainBuilder(
            new SubscriptionExistsValidator,
            new PlanExistsValidator,
            new CanDowngradeValidator($subscriptionService),
            new CanUpgradeValidator($subscriptionService),
            new UsageLimitsValidator($usageValidationService),
        );
    }

    /**
     * @param  \Closure(SubscriptionUpgradeBillingServiceContract): void  $configure
     */
    private function mockBillingService(\Closure $configure): SubscriptionUpgradeBillingServiceContract
    {
        $billingService = $this->createMock(SubscriptionUpgradeBillingServiceContract::class);
        $configure($billingService);

        return $billingService;
    }
}
