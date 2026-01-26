<?php

declare(strict_types=1);

namespace Tests\Unit\States\Subscription;

use App\Models\Subscription;
use App\States\Subscription\ActiveState;
use Tests\TestCase;

final class ActiveStateTest extends TestCase
{
    public function test_can_upgrade_returns_true(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertTrue($state->canUpgrade());
    }

    public function test_can_downgrade_returns_true(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertTrue($state->canDowngrade());
    }

    public function test_can_cancel_returns_true(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertTrue($state->canCancel());
    }

    public function test_can_resume_returns_false(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertFalse($state->canResume());
    }

    public function test_get_display_name_returns_active(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertSame('Active', $state->getDisplayName());
    }

    public function test_get_description_returns_active_message(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $this->assertSame('Your subscription is active', $state->getDescription());
    }

    public function test_get_allowed_actions_returns_all_available_actions(): void
    {
        $subscription = new Subscription;
        $state = new ActiveState($subscription);

        $expected = ['upgrade', 'downgrade', 'cancel', 'change_billing_cycle'];
        $this->assertSame($expected, $state->getAllowedActions());
    }
}
