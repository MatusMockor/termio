<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Subscription\SubscriptionImmediateUpgradeAction;
use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionUpgradeBillingServiceContract;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsUpgradeValidationChain;
use Tests\TestCase;

final class SubscriptionControllerTest extends TestCase
{
    use BuildsUpgradeValidationChain;
    use RefreshDatabase;

    private Plan $freePlan;

    private Plan $easyPlan;

    private Plan $smartPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan = Plan::factory()->create([
            'name' => 'FREE',
            'slug' => 'free',
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => false,
            ],
            'limits' => [
                'reservations_per_month' => 150,
                'users' => 1,
                'services' => 10,
            ],
        ]);

        $this->easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
            'monthly_price' => 5.90,
            'yearly_price' => 53.00,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'stripe_monthly_price_id' => 'price_easy_monthly',
            'stripe_yearly_price_id' => 'price_easy_yearly',
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => true,
            ],
            'limits' => [
                'reservations_per_month' => 500,
                'users' => 3,
                'services' => 25,
            ],
        ]);

        $this->smartPlan = Plan::factory()->create([
            'name' => 'SMART',
            'slug' => 'smart',
            'monthly_price' => 14.90,
            'yearly_price' => 134.00,
            'sort_order' => 2,
            'is_active' => true,
            'is_public' => true,
            'stripe_monthly_price_id' => 'price_smart_monthly',
            'stripe_yearly_price_id' => 'price_smart_yearly',
            'features' => [
                'online_booking_widget' => true,
                'manual_reservations' => true,
                'email_reminders' => true,
                'google_calendar_sync' => true,
                'api_access' => true,
            ],
            'limits' => [
                'reservations_per_month' => -1,
                'users' => -1,
                'services' => -1,
            ],
        ]);

        $this->createTenantWithOwner();
    }

    // ==========================================
    // Create Subscription Tests (Validation & Auth)
    // ==========================================

    public function test_create_subscription_requires_valid_plan(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => 99999,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['plan_id']);
    }

    public function test_create_subscription_requires_billing_cycle(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_create_subscription_validates_billing_cycle_values(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'weekly',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_create_subscription_accepts_monthly_billing_cycle(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->freePlan->id,
            'billing_cycle' => 'monthly',
            'start_trial' => false,
        ]);

        // Free plan doesn't require Stripe, so this should work
        $response->assertCreated();
    }

    public function test_create_subscription_accepts_yearly_billing_cycle(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->freePlan->id,
            'billing_cycle' => 'yearly',
            'start_trial' => false,
        ]);

        $response->assertCreated();
    }

    public function test_create_subscription_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.store'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_subscription_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_create_free_subscription(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.store'), [
            'plan_id' => $this->freePlan->id,
            'billing_cycle' => 'monthly',
            'start_trial' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Subscription created successfully.');

        $this->assertDatabaseHas(Subscription::class, [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
        ]);
    }

    // ==========================================
    // Get Current Subscription Tests
    // ==========================================

    public function test_user_can_get_current_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.show'));

        $response->assertOk();
        $response->assertJsonPath('data.id', $subscription->id);
        $response->assertJsonPath('plan.id', $this->easyPlan->id);
        $response->assertJsonStructure([
            'data' => ['id', 'plan_id', 'stripe_status', 'billing_cycle'],
            'plan' => ['id', 'name', 'slug'],
            'is_on_trial',
            'trial_days_remaining',
            'pending_change',
        ]);
    }

    public function test_user_without_subscription_gets_null_data(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('subscriptions.show'));

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }

    public function test_user_on_trial_gets_trial_info(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.show'));

        $response->assertOk();
        $response->assertJsonPath('is_on_trial', true);
        $this->assertGreaterThan(0, $response->json('trial_days_remaining'));
    }

    public function test_user_with_scheduled_downgrade_sees_pending_change(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
            'scheduled_plan_id' => $this->easyPlan->id,
            'scheduled_change_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.show'));

        $response->assertOk();
        $this->assertNotNull($response->json('pending_change'));
    }

    public function test_get_subscription_requires_authentication(): void
    {
        $response = $this->getJson(route('subscriptions.show'));

        $response->assertUnauthorized();
    }

    public function test_get_subscription_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('subscriptions.show'));

        $response->assertForbidden();
    }

    // ==========================================
    // Upgrade Subscription Tests (Validation & Auth)
    // ==========================================

    public function test_user_cannot_upgrade_to_lower_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_user_cannot_upgrade_to_same_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertStatus(400);
    }

    public function test_upgrade_requires_valid_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['plan_id']);
    }

    public function test_upgrade_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->smartPlan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_upgrade_requires_owner_role(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
        ]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertForbidden();
    }

    public function test_free_subscription_upgrade_requires_default_payment_method(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
            'stripe_id' => 'free_'.$this->tenant->id,
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Payment method is required for paid plans.');
    }

    public function test_free_subscription_upgrade_fails_when_target_plan_has_no_price_id(): void
    {
        $this->easyPlan->update([
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);

        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => 'active',
            'stripe_id' => 'free_'.$this->tenant->id,
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Stripe error: No Stripe price ID configured for this plan.');
    }

    public function test_immediate_upgrade_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->smartPlan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_immediate_upgrade_requires_owner_role(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
        ]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->smartPlan->id,
        ]);

        $response->assertForbidden();
    }

    public function test_immediate_upgrade_requires_valid_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['plan_id']);
    }

    public function test_immediate_upgrade_validates_billing_cycle_values(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->smartPlan->id,
            'billing_cycle' => 'weekly',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_user_cannot_immediately_upgrade_to_lower_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_immediate_free_subscription_upgrade_requires_default_payment_method(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->freePlan->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'stripe_id' => 'free_'.$this->tenant->id,
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->easyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Payment method is required for paid plans.');
    }

    public function test_immediate_upgrade_trial_subscription_returns_success_payload_and_clears_pending_flags(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()
            ->forTenant($this->tenant)
            ->forPlan($this->easyPlan)
            ->create([
                'stripe_id' => 'sub_trial_123',
                'stripe_status' => SubscriptionStatus::Trialing->value,
                'trial_ends_at' => now()->addDays(7),
                'ends_at' => now()->addDays(2),
                'scheduled_plan_id' => $this->freePlan->id,
                'scheduled_change_at' => now()->addDay(),
                'billing_cycle' => 'monthly',
            ]);

        $validationChainBuilder = $this->createUpgradeValidationChainBuilder();

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects($this->once())
            ->method('findById')
            ->with($subscription->id)
            ->willReturn($subscription);
        $subscriptionRepository->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn (callable $callback): Subscription => $callback());
        $subscriptionRepository->expects($this->once())
            ->method('update')
            ->with(
                $subscription,
                $this->callback(function (array $data): bool {
                    $this->assertArrayHasKey('ends_at', $data);
                    $this->assertNull($data['ends_at']);
                    $this->assertArrayHasKey('scheduled_plan_id', $data);
                    $this->assertNull($data['scheduled_plan_id']);
                    $this->assertArrayHasKey('scheduled_change_at', $data);
                    $this->assertNull($data['scheduled_change_at']);

                    return true;
                }),
            )
            ->willReturnCallback(
                static function (Subscription $subscriptionModel, array $data): Subscription {
                    $subscriptionModel->forceFill($data);

                    return $subscriptionModel;
                }
            );

        $planRepository = $this->createMock(PlanRepository::class);
        $planRepository->expects($this->once())
            ->method('findById')
            ->with($this->smartPlan->id)
            ->willReturn($this->smartPlan);

        $billingService = $this->createMock(SubscriptionUpgradeBillingServiceContract::class);
        $billingService->expects($this->once())
            ->method('resolvePriceId')
            ->with($this->smartPlan, BillingCycle::Monthly)
            ->willReturn('price_smart_monthly');
        $billingService->expects($this->once())
            ->method('isFreeSubscription')
            ->with($subscription)
            ->willReturn(false);
        $billingService->expects($this->once())
            ->method('resumeCanceledPaidSubscription')
            ->with($subscription);
        $billingService->expects($this->once())
            ->method('swapPaidSubscription')
            ->with($subscription, 'price_smart_monthly');
        $billingService->expects($this->never())
            ->method('swapPaidSubscriptionAndInvoice');

        $this->app->instance(
            SubscriptionImmediateUpgradeAction::class,
            new SubscriptionImmediateUpgradeAction(
                $subscriptionRepository,
                $planRepository,
                $validationChainBuilder,
                $billingService,
            ),
        );

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.upgrade-immediate'), [
            'plan_id' => $this->smartPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Subscription upgraded immediately.');
        $response->assertJsonPath('data.plan_id', $this->smartPlan->id);
        $response->assertJsonPath('data.ends_at', null);
        $response->assertJsonPath('data.scheduled_plan_id', null);
        $response->assertJsonPath('data.billing_cycle', 'monthly');

        $responseTrialEndsAt = $response->json('data.trial_ends_at');
        $this->assertNotNull($responseTrialEndsAt);

        $this->assertTrue(Carbon::parse((string) $responseTrialEndsAt)->equalTo($subscription->trial_ends_at));
    }

    // ==========================================
    // Downgrade Subscription Tests (Validation & Auth)
    // ==========================================

    public function test_user_cannot_downgrade_to_higher_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.downgrade'), [
            'plan_id' => $this->smartPlan->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_downgrade_fails_if_usage_exceeds_limits(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
        ]);

        // Create services exceeding free plan limit (10)
        Service::factory()->count(15)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.downgrade'), [
            'plan_id' => $this->freePlan->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_downgrade_requires_valid_plan(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('subscriptions.downgrade'), [
            'plan_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['plan_id']);
    }

    public function test_downgrade_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.downgrade'), [
            'plan_id' => $this->freePlan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_downgrade_requires_owner_role(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
        ]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.downgrade'), [
            'plan_id' => $this->easyPlan->id,
        ]);

        $response->assertForbidden();
    }

    // ==========================================
    // Cancel Subscription Tests (Validation & Auth)
    // ==========================================

    public function test_cancel_without_subscription_returns_404(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.cancel'), [
            'reason' => 'No subscription',
        ]);

        $response->assertNotFound();
    }

    public function test_cancel_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.cancel'), [
            'reason' => 'Unauthenticated',
        ]);

        $response->assertUnauthorized();
    }

    public function test_cancel_requires_owner_role(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.cancel'), [
            'reason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    // ==========================================
    // Resume Subscription Tests (Validation & Auth)
    // ==========================================

    public function test_resume_without_subscription_returns_404(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('subscriptions.resume'));

        $response->assertNotFound();
    }

    public function test_resume_requires_authentication(): void
    {
        $response = $this->postJson(route('subscriptions.resume'));

        $response->assertUnauthorized();
    }

    public function test_resume_requires_owner_role(): void
    {
        Subscription::factory()->canceled()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
            'ends_at' => now()->addDays(15),
        ]);

        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->postJson(route('subscriptions.resume'));

        $response->assertForbidden();
    }

    // ==========================================
    // Usage Endpoint Tests
    // ==========================================

    public function test_user_can_get_usage_stats(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'reservations',
                'users',
                'services',
            ],
            'warnings' => [
                'reservations',
                'users',
                'services',
            ],
        ]);
    }

    public function test_usage_stats_without_subscription_works(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('subscriptions.usage'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'warnings',
        ]);
    }

    public function test_usage_requires_authentication(): void
    {
        $response = $this->getJson(route('subscriptions.usage'));

        $response->assertUnauthorized();
    }

    public function test_usage_requires_owner_role(): void
    {
        $staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)->getJson(route('subscriptions.usage'));

        $response->assertForbidden();
    }

    // ==========================================
    // Subscription Model Tests
    // ==========================================

    public function test_subscription_is_active_when_stripe_status_is_active(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
        ]);

        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_is_not_active_when_canceled(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'canceled',
        ]);

        $this->assertFalse($subscription->isActive());
    }

    public function test_subscription_on_trial_when_trial_ends_at_is_future(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($subscription->onTrial());
    }

    public function test_subscription_not_on_trial_when_trial_ended(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($subscription->onTrial());
    }

    public function test_subscription_has_scheduled_plan_change(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->smartPlan->id,
            'stripe_status' => 'active',
            'scheduled_plan_id' => $this->easyPlan->id,
            'scheduled_change_at' => now()->addMonth(),
        ]);

        $this->assertTrue($subscription->hasScheduledPlanChange());
    }

    public function test_subscription_canceled_when_ends_at_is_set(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($subscription->canceled());
    }

    public function test_subscription_on_grace_period_when_ends_at_is_future(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'active',
            'ends_at' => now()->addDays(15),
        ]);

        $this->assertTrue($subscription->onGracePeriod());
    }

    public function test_subscription_ended_when_ends_at_is_past(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->easyPlan->id,
            'stripe_status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($subscription->ended());
    }
}
