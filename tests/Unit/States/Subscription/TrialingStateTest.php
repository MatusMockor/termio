<?php

declare(strict_types=1);

namespace Tests\Unit\States\Subscription;

use App\Models\Subscription;
use App\States\Subscription\TrialingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TrialingStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upgrade_returns_true(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertTrue($state->canUpgrade());
    }

    public function test_can_downgrade_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertFalse($state->canDowngrade());
    }

    public function test_can_cancel_returns_true(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertTrue($state->canCancel());
    }

    public function test_can_resume_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertFalse($state->canResume());
    }

    public function test_get_display_name_returns_on_trial(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertSame('On Trial', $state->getDisplayName());
    }

    public function test_get_description_shows_days_remaining(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->trial_ends_at = Carbon::now()->addDays(7);
        $state = new TrialingState($subscription);

        $this->assertSame('Trial ends in 7 days', $state->getDescription());
    }

    public function test_get_description_shows_singular_day(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->trial_ends_at = Carbon::now()->addDay();
        $state = new TrialingState($subscription);

        $this->assertSame('Trial ends in 1 day', $state->getDescription());
    }

    public function test_get_description_when_trial_ends_at_is_null(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->trial_ends_at = null;
        $state = new TrialingState($subscription);

        $this->assertSame('Your trial is active', $state->getDescription());
    }

    public function test_get_description_when_trial_has_ended(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->trial_ends_at = Carbon::now()->subDay();
        $state = new TrialingState($subscription);

        $this->assertSame('Your trial has ended', $state->getDescription());
    }

    public function test_get_allowed_actions_returns_upgrade_and_cancel(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new TrialingState($subscription);

        $this->assertSame(['upgrade', 'cancel'], $state->getAllowedActions());
    }
}
