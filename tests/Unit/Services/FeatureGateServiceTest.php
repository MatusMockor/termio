<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Enums\Feature;
use App\Exceptions\FeatureNotAvailableException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Subscription\FeatureGateService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class FeatureGateServiceTest extends TestCase
{
    private FeatureGateService $service;

    private SubscriptionServiceContract&MockInterface $subscriptionService;

    private PlanRepository&MockInterface $planRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(SubscriptionServiceContract::class);
        $this->planRepository = Mockery::mock(PlanRepository::class);

        $this->service = new FeatureGateService(
            $this->subscriptionService,
            $this->planRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_access_returns_true_when_tenant_has_feature(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('hasFeature')
            ->with($tenant, 'google_calendar_sync')
            ->once()
            ->andReturn(true);

        $result = $this->service->canAccess($tenant, 'google_calendar_sync');

        $this->assertTrue($result);
    }

    public function test_can_access_returns_false_when_tenant_lacks_feature(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('hasFeature')
            ->with($tenant, 'google_calendar_sync')
            ->once()
            ->andReturn(false);

        $result = $this->service->canAccess($tenant, 'google_calendar_sync');

        $this->assertFalse($result);
    }

    public function test_can_access_feature_works_with_enum(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('hasFeature')
            ->with($tenant, Feature::GoogleCalendarSync->value)
            ->once()
            ->andReturn(true);

        $result = $this->service->canAccessFeature($tenant, Feature::GoogleCalendarSync);

        $this->assertTrue($result);
    }

    public function test_get_required_plan_returns_plan_for_known_feature(): void
    {
        $easyPlan = new Plan;
        $easyPlan->name = 'EASY';
        $easyPlan->slug = 'easy';

        $this->planRepository
            ->shouldReceive('findBySlug')
            ->with('easy')
            ->once()
            ->andReturn($easyPlan);

        $result = $this->service->getRequiredPlan('google_calendar_sync');

        $this->assertNotNull($result);
        $this->assertEquals('easy', $result->slug);
    }

    public function test_get_required_plan_returns_null_for_unknown_feature(): void
    {
        $result = $this->service->getRequiredPlan('unknown_feature');

        $this->assertNull($result);
    }

    public function test_authorize_does_not_throw_when_feature_available(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('hasFeature')
            ->with($tenant, 'google_calendar_sync')
            ->once()
            ->andReturn(true);

        // Should not throw
        $this->service->authorize($tenant, 'google_calendar_sync');

        $this->assertTrue(true);
    }

    public function test_authorize_throws_when_feature_not_available(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('hasFeature')
            ->with($tenant, 'google_calendar_sync')
            ->once()
            ->andReturn(false);

        $this->expectException(FeatureNotAvailableException::class);

        $this->service->authorize($tenant, 'google_calendar_sync');
    }

    public function test_deny_with_upgrade_message_returns_403_response(): void
    {
        $easyPlan = new Plan;
        $easyPlan->name = 'EASY';
        $easyPlan->slug = 'easy';
        $easyPlan->monthly_price = '5.90';

        $this->planRepository
            ->shouldReceive('findBySlug')
            ->with('easy')
            ->once()
            ->andReturn($easyPlan);

        $response = $this->service->denyWithUpgradeMessage('google_calendar_sync', 'free');

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('feature_not_available', $content['error']);
        $this->assertEquals('google_calendar_sync', $content['feature']);
        $this->assertEquals('free', $content['current_plan']);
        $this->assertEquals('easy', $content['required_plan']['slug']);
        $this->assertEquals('/billing/upgrade', $content['upgrade_url']);
    }

    public function test_get_feature_value_returns_value_from_subscription_service(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService
            ->shouldReceive('getFeatureValue')
            ->with($tenant, 'calendar_view')
            ->once()
            ->andReturn('advanced');

        $result = $this->service->getFeatureValue($tenant, 'calendar_view');

        $this->assertEquals('advanced', $result);
    }
}
