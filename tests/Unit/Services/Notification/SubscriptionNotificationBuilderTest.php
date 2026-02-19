<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notification;

use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\SubscriptionCanceledNotification;
use App\Notifications\SubscriptionDowngradedNotification;
use App\Notifications\SubscriptionDowngradeScheduledNotification;
use App\Notifications\SubscriptionUpgradedNotification;
use App\Notifications\TrialEndedNotification;
use App\Services\Notification\SubscriptionNotificationBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class SubscriptionNotificationBuilderTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionNotificationBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SubscriptionNotificationBuilder;
    }

    public function test_fluent_interface_returns_self(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $previousPlan = Plan::factory()->create();
        $date = Carbon::now();

        $result = $this->builder
            ->forTenant($tenant)
            ->withPlan($plan)
            ->withPreviousPlan($previousPlan)
            ->effectiveAt($date)
            ->withReason('Test reason')
            ->withMetadata(['key' => 'value'])
            ->viaChannels(['mail', 'database'])
            ->viaMail();

        $this->assertInstanceOf(SubscriptionNotificationBuilder::class, $result);
    }

    public function test_build_downgrade_scheduled_creates_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $currentPlan = Plan::factory()->create();
        $currentPlan->id = 1;
        $currentPlan->name = 'Pro';
        $currentPlan->features = [];
        $currentPlan->limits = [];

        $newPlan = Plan::factory()->create();
        $newPlan->id = 2;
        $newPlan->name = 'Basic';
        $newPlan->features = [];
        $newPlan->limits = [];

        $effectiveDate = Carbon::now()->addDays(30);

        $notification = $this->builder
            ->forTenant($tenant)
            ->withPreviousPlan($currentPlan)
            ->withPlan($newPlan)
            ->effectiveAt($effectiveDate)
            ->buildDowngradeScheduled();

        $this->assertInstanceOf(SubscriptionDowngradeScheduledNotification::class, $notification);
    }

    public function test_build_upgraded_creates_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $oldPlan = Plan::factory()->create();
        $oldPlan->id = 1;
        $oldPlan->name = 'Basic';
        $oldPlan->features = [];
        $oldPlan->limits = [];

        $newPlan = Plan::factory()->create();
        $newPlan->id = 2;
        $newPlan->name = 'Pro';
        $newPlan->features = [];
        $newPlan->limits = [];

        $notification = $this->builder
            ->forTenant($tenant)
            ->withPreviousPlan($oldPlan)
            ->withPlan($newPlan)
            ->buildUpgraded();

        $this->assertInstanceOf(SubscriptionUpgradedNotification::class, $notification);
    }

    public function test_build_canceled_creates_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $accessEndsAt = Carbon::now()->addDays(30);

        $notification = $this->builder
            ->forTenant($tenant)
            ->effectiveAt($accessEndsAt)
            ->buildCanceled();

        $this->assertInstanceOf(SubscriptionCanceledNotification::class, $notification);
    }

    public function test_build_trial_ended_with_conversion(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $notification = $this->builder
            ->forTenant($tenant)
            ->buildTrialEnded(converted: true);

        $this->assertInstanceOf(TrialEndedNotification::class, $notification);
    }

    public function test_build_trial_ended_without_conversion(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $notification = $this->builder
            ->forTenant($tenant)
            ->buildTrialEnded(converted: false);

        $this->assertInstanceOf(TrialEndedNotification::class, $notification);
    }

    public function test_build_downgraded_creates_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;
        $tenant->name = 'Test Tenant';

        $plan = Plan::factory()->create();
        $plan->id = 1;
        $plan->name = 'Free';

        $notification = $this->builder
            ->forTenant($tenant)
            ->withPlan($plan)
            ->buildDowngraded();

        $this->assertInstanceOf(SubscriptionDowngradedNotification::class, $notification);
    }

    public function test_build_downgrade_scheduled_throws_without_tenant(): void
    {
        $plan = Plan::factory()->create();
        $previousPlan = Plan::factory()->create();
        $date = Carbon::now();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder
            ->withPlan($plan)
            ->withPreviousPlan($previousPlan)
            ->effectiveAt($date)
            ->buildDowngradeScheduled();
    }

    public function test_build_downgrade_scheduled_throws_without_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $previousPlan = Plan::factory()->create();
        $date = Carbon::now();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target plan is required. Call withPlan() before building.');

        $this->builder
            ->forTenant($tenant)
            ->withPreviousPlan($previousPlan)
            ->effectiveAt($date)
            ->buildDowngradeScheduled();
    }

    public function test_build_downgrade_scheduled_throws_without_previous_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $date = Carbon::now();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Previous plan is required. Call withPreviousPlan() before building.');

        $this->builder
            ->forTenant($tenant)
            ->withPlan($plan)
            ->effectiveAt($date)
            ->buildDowngradeScheduled();
    }

    public function test_build_downgrade_scheduled_throws_without_effective_date(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $previousPlan = Plan::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Effective date is required. Call effectiveAt() before building.');

        $this->builder
            ->forTenant($tenant)
            ->withPlan($plan)
            ->withPreviousPlan($previousPlan)
            ->buildDowngradeScheduled();
    }

    public function test_build_upgraded_throws_without_tenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder
            ->withPlan(Plan::factory()->create())
            ->withPreviousPlan(Plan::factory()->create())
            ->buildUpgraded();
    }

    public function test_build_upgraded_throws_without_new_plan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('New plan is required. Call withPlan() before building.');

        $this->builder
            ->forTenant(Tenant::factory()->create())
            ->withPreviousPlan(Plan::factory()->create())
            ->buildUpgraded();
    }

    public function test_build_upgraded_throws_without_previous_plan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Previous plan is required. Call withPreviousPlan() before building.');

        $this->builder
            ->forTenant(Tenant::factory()->create())
            ->withPlan(Plan::factory()->create())
            ->buildUpgraded();
    }

    public function test_build_canceled_throws_without_tenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder
            ->effectiveAt(Carbon::now())
            ->buildCanceled();
    }

    public function test_build_canceled_throws_without_effective_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Effective date is required. Call effectiveAt() before building.');

        $this->builder
            ->forTenant(Tenant::factory()->create())
            ->buildCanceled();
    }

    public function test_build_trial_ended_throws_without_tenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder->buildTrialEnded(converted: false);
    }

    public function test_build_downgraded_throws_without_tenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder
            ->withPlan(Plan::factory()->create())
            ->buildDowngraded();
    }

    public function test_build_downgraded_throws_without_plan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Plan is required. Call withPlan() before building.');

        $this->builder
            ->forTenant(Tenant::factory()->create())
            ->buildDowngraded();
    }

    public function test_reset_clears_all_properties(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();
        $previousPlan = Plan::factory()->create();
        $date = Carbon::now();

        $this->builder
            ->forTenant($tenant)
            ->withPlan($plan)
            ->withPreviousPlan($previousPlan)
            ->effectiveAt($date)
            ->withReason('Test reason')
            ->withMetadata(['key' => 'value'])
            ->viaChannels(['mail', 'database'])
            ->reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant is required. Call forTenant() before building.');

        $this->builder->buildTrialEnded(converted: false);
    }

    public function test_reset_returns_self_for_method_chaining(): void
    {
        $result = $this->builder->reset();

        $this->assertInstanceOf(SubscriptionNotificationBuilder::class, $result);
    }

    public function test_get_reason_returns_configured_reason(): void
    {
        $this->builder->withReason('Test reason');

        $this->assertSame('Test reason', $this->builder->getReason());
    }

    public function test_get_reason_returns_null_when_not_set(): void
    {
        $this->assertNull($this->builder->getReason());
    }

    public function test_get_metadata_returns_configured_metadata(): void
    {
        $metadata = ['key' => 'value', 'another' => 123];
        $this->builder->withMetadata($metadata);

        $this->assertSame($metadata, $this->builder->getMetadata());
    }

    public function test_get_metadata_returns_empty_array_when_not_set(): void
    {
        $this->assertSame([], $this->builder->getMetadata());
    }

    public function test_get_channels_returns_configured_channels(): void
    {
        $channels = ['mail', 'database', 'slack'];
        $this->builder->viaChannels($channels);

        $this->assertSame($channels, $this->builder->getChannels());
    }

    public function test_get_channels_returns_mail_by_default(): void
    {
        $this->assertSame(['mail'], $this->builder->getChannels());
    }

    public function test_via_mail_sets_only_mail_channel(): void
    {
        $this->builder
            ->viaChannels(['mail', 'database', 'slack'])
            ->viaMail();

        $this->assertSame(['mail'], $this->builder->getChannels());
    }

    public function test_builder_can_be_reused_after_build(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant1->id = 1;
        $tenant1->name = 'Tenant 1';

        $tenant2 = Tenant::factory()->create();
        $tenant2->id = 2;
        $tenant2->name = 'Tenant 2';

        $notification1 = $this->builder
            ->forTenant($tenant1)
            ->buildTrialEnded(converted: true);

        // Builder should still work without reset, allowing for chain modifications
        $notification2 = $this->builder
            ->forTenant($tenant2)
            ->buildTrialEnded(converted: false);

        $this->assertInstanceOf(TrialEndedNotification::class, $notification1);
        $this->assertInstanceOf(TrialEndedNotification::class, $notification2);
    }
}
