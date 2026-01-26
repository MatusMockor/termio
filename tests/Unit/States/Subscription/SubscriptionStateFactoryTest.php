<?php

declare(strict_types=1);

namespace Tests\Unit\States\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Subscription\SubscriptionStateFactory;
use App\States\Subscription\ActiveState;
use App\States\Subscription\CanceledState;
use App\States\Subscription\PastDueState;
use App\States\Subscription\TrialingState;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

final class SubscriptionStateFactoryTest extends TestCase
{
    private SubscriptionStateFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new SubscriptionStateFactory;
    }

    public function test_creates_trialing_state_when_trial_ends_at_is_in_future(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = Carbon::now()->addDays(7);
        $subscription->stripe_status = SubscriptionStatus::Trialing;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(TrialingState::class, $state);
    }

    public function test_creates_trialing_state_even_with_active_status_when_on_trial(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = Carbon::now()->addDays(7);
        $subscription->stripe_status = SubscriptionStatus::Active;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(TrialingState::class, $state);
    }

    public function test_creates_canceled_state_when_ends_at_is_set_and_on_grace_period(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = Carbon::now()->addDays(7);
        $subscription->stripe_status = SubscriptionStatus::Active;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(CanceledState::class, $state);
    }

    public function test_creates_canceled_state_when_ends_at_is_in_past(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = Carbon::now()->subDay();
        $subscription->stripe_status = SubscriptionStatus::Canceled;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(CanceledState::class, $state);
    }

    public function test_creates_past_due_state_when_status_is_past_due(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::PastDue;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(PastDueState::class, $state);
    }

    public function test_creates_active_state_when_status_is_active(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Active;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(ActiveState::class, $state);
    }

    public function test_creates_active_state_for_incomplete_status(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Incomplete;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(ActiveState::class, $state);
    }

    public function test_creates_active_state_for_unpaid_status(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Unpaid;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(ActiveState::class, $state);
    }

    public function test_creates_active_state_for_paused_status(): void
    {
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Paused;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(ActiveState::class, $state);
    }

    public function test_trialing_takes_precedence_over_canceled(): void
    {
        // Edge case: subscription has both trial_ends_at and ends_at in future
        $subscription = new Subscription;
        $subscription->trial_ends_at = Carbon::now()->addDays(7);
        $subscription->ends_at = Carbon::now()->addDays(3);
        $subscription->stripe_status = SubscriptionStatus::Trialing;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(TrialingState::class, $state);
    }

    public function test_canceled_takes_precedence_over_past_due(): void
    {
        // Edge case: subscription is canceled but also past due
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = Carbon::now()->addDays(7);
        $subscription->stripe_status = SubscriptionStatus::PastDue;

        $state = $this->factory->create($subscription);

        $this->assertInstanceOf(CanceledState::class, $state);
    }

    public function test_throws_for_incomplete_expired_status(): void
    {
        $subscription = new Subscription;
        $subscription->id = 123;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::IncompleteExpired;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown subscription state for subscription ID: 123');

        $this->factory->create($subscription);
    }

    public function test_handles_trialing_status_without_trial_end_date(): void
    {
        // Edge case: status is trialing but trial_ends_at is null
        $subscription = new Subscription;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Trialing;

        $state = $this->factory->create($subscription);

        // Treated as active since there's no actual trial date
        $this->assertInstanceOf(ActiveState::class, $state);
    }

    public function test_handles_trialing_status_with_expired_trial_date(): void
    {
        // Edge case: status is trialing but trial has already ended
        $subscription = new Subscription;
        $subscription->trial_ends_at = Carbon::now()->subDay();
        $subscription->ends_at = null;
        $subscription->stripe_status = SubscriptionStatus::Trialing;

        $state = $this->factory->create($subscription);

        // Treated as active since trial date has passed
        $this->assertInstanceOf(ActiveState::class, $state);
    }
}
