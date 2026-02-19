<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Validation\Validators\CanUpgradeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class CanUpgradeValidatorTest extends TestCase
{
    use RefreshDatabase;

    private CanUpgradeValidator $validator;

    private SubscriptionServiceContract&MockObject $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = $this->createMock(SubscriptionServiceContract::class);
        $this->validator = new CanUpgradeValidator($this->subscriptionService);
    }

    public function test_passes_when_can_upgrade(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;

        $currentPlan = Plan::factory()->create();
        $currentPlan->id = 1;
        $currentPlan->name = 'Basic';

        $newPlan = Plan::factory()->create();
        $newPlan->id = 2;
        $newPlan->name = 'Pro';

        $subscription = Subscription::factory()->create();
        $subscription->id = 1;
        $subscription->setRelation('tenant', $tenant);
        $subscription->setRelation('plan', $currentPlan);

        $context = new ValidationContext(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->expects($this->once())
            ->method('canUpgradeTo')
            ->with($tenant, $newPlan)
            ->willReturn(true);

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_cannot_upgrade(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;

        $currentPlan = Plan::factory()->create();
        $currentPlan->id = 2;
        $currentPlan->name = 'Pro';

        $newPlan = Plan::factory()->create();
        $newPlan->id = 1;
        $newPlan->name = 'Basic';

        $subscription = Subscription::factory()->create();
        $subscription->id = 1;
        $subscription->setRelation('tenant', $tenant);
        $subscription->setRelation('plan', $currentPlan);

        $context = new ValidationContext(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->expects($this->once())
            ->method('canUpgradeTo')
            ->with($tenant, $newPlan)
            ->willReturn(false);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Cannot upgrade from Pro to Basic.');

        $this->validator->validate($context);
    }

    public function test_skips_validation_when_tenant_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: Plan::factory()->create(),
            tenant: null,
        );

        $this->subscriptionService
            ->expects($this->never())
            ->method('canUpgradeTo');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_skips_validation_when_plan_is_null(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->expects($this->never())
            ->method('canUpgradeTo');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }
}
