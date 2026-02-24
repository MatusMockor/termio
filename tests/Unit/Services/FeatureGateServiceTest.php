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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class FeatureGateServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureGateService $service;

    private SubscriptionServiceContract&MockObject $subscriptionService;

    private PlanRepository&MockObject $planRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = $this->createMock(SubscriptionServiceContract::class);
        $this->planRepository = $this->createMock(PlanRepository::class);

        $this->service = new FeatureGateService(
            $this->subscriptionService,
            $this->planRepository,
        );
    }

    public function test_can_access_returns_true_when_tenant_has_feature(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::GoogleCalendarSync->value;

        $this->subscriptionService
            ->expects($this->once())
            ->method('hasFeature')
            ->with($tenant, $feature)
            ->willReturn(true);

        $result = $this->service->canAccess($tenant, $feature);

        $this->assertTrue($result);
    }

    public function test_can_access_returns_false_when_tenant_lacks_feature(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::GoogleCalendarSync->value;

        $this->subscriptionService
            ->expects($this->once())
            ->method('hasFeature')
            ->with($tenant, $feature)
            ->willReturn(false);

        $result = $this->service->canAccess($tenant, $feature);

        $this->assertFalse($result);
    }

    public function test_can_access_feature_works_with_enum(): void
    {
        $tenant = Tenant::factory()->create();

        $this->subscriptionService
            ->expects($this->once())
            ->method('hasFeature')
            ->with($tenant, Feature::GoogleCalendarSync->value)
            ->willReturn(true);

        $result = $this->service->canAccessFeature($tenant, Feature::GoogleCalendarSync);

        $this->assertTrue($result);
    }

    public function test_get_required_plan_returns_plan_for_known_feature(): void
    {
        $feature = Feature::GoogleCalendarSync->value;
        $easyPlan = Plan::factory()->create([
            'name' => 'EASY',
            'slug' => 'easy',
        ]);

        $this->planRepository
            ->expects($this->once())
            ->method('findBySlug')
            ->with('easy')
            ->willReturn($easyPlan);

        $result = $this->service->getRequiredPlan($feature);

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
        $tenant = Tenant::factory()->create();
        $feature = Feature::GoogleCalendarSync->value;

        $this->subscriptionService
            ->expects($this->once())
            ->method('hasFeature')
            ->with($tenant, $feature)
            ->willReturn(true);

        // Should not throw
        $this->service->authorize($tenant, $feature);

        $this->assertTrue(true);
    }

    public function test_authorize_throws_when_feature_not_available(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::GoogleCalendarSync->value;

        $this->subscriptionService
            ->expects($this->once())
            ->method('hasFeature')
            ->with($tenant, $feature)
            ->willReturn(false);

        $this->expectException(FeatureNotAvailableException::class);

        $this->service->authorize($tenant, $feature);
    }

    public function test_build_upgrade_message_returns_expected_payload(): void
    {
        $feature = Feature::GoogleCalendarSync->value;
        $currentPlan = fake()->word();
        $planName = fake()->word();
        $planPrice = number_format(fake()->randomFloat(2, 1, 100), 2, '.', '');
        $easyPlan = Plan::factory()->create([
            'name' => $planName,
            'slug' => 'easy',
            'monthly_price' => $planPrice,
        ]);

        $this->planRepository
            ->expects($this->once())
            ->method('findBySlug')
            ->with('easy')
            ->willReturn($easyPlan);

        $payload = $this->service->buildUpgradeMessage($feature, $currentPlan);

        $this->assertEquals('feature_not_available', $payload->error);
        $this->assertEquals($feature, $payload->feature);
        $this->assertEquals($currentPlan, $payload->currentPlan);
        $this->assertEquals($planName, $payload->requiredPlan->name);
        $this->assertEquals('easy', $payload->requiredPlan->slug);
        $this->assertEquals($planPrice, $payload->requiredPlan->monthlyPrice);
        $this->assertEquals((string) config('billing.upgrade_url', '/billing/upgrade'), $payload->upgradeUrl);
    }

    public function test_get_feature_value_returns_value_from_subscription_service(): void
    {
        $tenant = Tenant::factory()->create();

        $this->subscriptionService
            ->expects($this->once())
            ->method('getFeatureValue')
            ->with($tenant, 'calendar_view')
            ->willReturn('advanced');

        $result = $this->service->getFeatureValue($tenant, 'calendar_view');

        $this->assertEquals('advanced', $result);
    }
}
