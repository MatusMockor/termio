<?php

declare(strict_types=1);

namespace Tests\Unit\States\Subscription;

use App\Models\Subscription;
use App\States\Subscription\PastDueState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PastDueStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upgrade_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertFalse($state->canUpgrade());
    }

    public function test_can_downgrade_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertFalse($state->canDowngrade());
    }

    public function test_can_cancel_returns_true(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertTrue($state->canCancel());
    }

    public function test_can_resume_returns_false(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertFalse($state->canResume());
    }

    public function test_get_display_name_returns_past_due(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertSame('Past Due', $state->getDisplayName());
    }

    public function test_get_description_returns_payment_failed_message(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $this->assertSame(
            'Payment failed. Please update your payment method.',
            $state->getDescription()
        );
    }

    public function test_get_allowed_actions_returns_update_payment_and_cancel(): void
    {
        $subscription = Subscription::factory()->create();
        $state = new PastDueState($subscription);

        $expected = ['update_payment_method', 'cancel'];
        $this->assertSame($expected, $state->getAllowedActions());
    }
}
