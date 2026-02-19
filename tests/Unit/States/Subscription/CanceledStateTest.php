<?php

declare(strict_types=1);

namespace Tests\Unit\States\Subscription;

use App\Models\Subscription;
use App\States\Subscription\CanceledState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class CanceledStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upgrade_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new CanceledState($subscription);

        $this->assertFalse($state->canUpgrade());
    }

    public function test_can_downgrade_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new CanceledState($subscription);

        $this->assertFalse($state->canDowngrade());
    }

    public function test_can_cancel_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new CanceledState($subscription);

        $this->assertFalse($state->canCancel());
    }

    public function test_can_resume_returns_true_when_on_grace_period(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->addDays(7);
        $state = new CanceledState($subscription);

        $this->assertTrue($state->canResume());
    }

    public function test_can_resume_returns_false_when_grace_period_ended(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->subDay();
        $state = new CanceledState($subscription);

        $this->assertFalse($state->canResume());
    }

    public function test_can_resume_returns_false_when_ends_at_is_null(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = null;
        $state = new CanceledState($subscription);

        $this->assertFalse($state->canResume());
    }

    public function test_get_display_name_returns_canceled(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new CanceledState($subscription);

        $this->assertSame('Canceled', $state->getDisplayName());
    }

    public function test_get_description_shows_days_remaining_when_on_grace_period(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->addDays(7);
        $state = new CanceledState($subscription);

        $this->assertSame('Subscription ends in 7 days', $state->getDescription());
    }

    public function test_get_description_shows_singular_day_when_one_day_left(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->addDay();
        $state = new CanceledState($subscription);

        $this->assertSame('Subscription ends in 1 day', $state->getDescription());
    }

    public function test_get_description_shows_ended_when_grace_period_passed(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->subDay();
        $state = new CanceledState($subscription);

        $this->assertSame('Subscription has ended', $state->getDescription());
    }

    public function test_get_description_when_ends_at_is_null(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = null;
        $state = new CanceledState($subscription);

        $this->assertSame('Subscription is canceled', $state->getDescription());
    }

    public function test_get_allowed_actions_returns_resume_when_on_grace_period(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->addDays(7);
        $state = new CanceledState($subscription);

        $this->assertSame(['resume'], $state->getAllowedActions());
    }

    public function test_get_allowed_actions_returns_resubscribe_when_grace_period_ended(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->ends_at = Carbon::now()->subDay();
        $state = new CanceledState($subscription);

        $this->assertSame(['resubscribe'], $state->getAllowedActions());
    }
}
