<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Validation\Validators\CanDowngradeValidator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CanDowngradeValidatorTest extends TestCase
{
    private CanDowngradeValidator $validator;

    private SubscriptionServiceContract&MockInterface $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(SubscriptionServiceContract::class);
        $this->validator = new CanDowngradeValidator($this->subscriptionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_passes_when_can_downgrade(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $currentPlan = new Plan;
        $currentPlan->id = 2;
        $currentPlan->name = 'Pro';

        $newPlan = new Plan;
        $newPlan->id = 1;
        $newPlan->name = 'Basic';

        $subscription = new Subscription;
        $subscription->id = 1;
        $subscription->setRelation('tenant', $tenant);
        $subscription->setRelation('plan', $currentPlan);

        $context = new ValidationContext(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->shouldReceive('canDowngradeTo')
            ->with($tenant, $newPlan)
            ->once()
            ->andReturn(true);

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_cannot_downgrade(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $currentPlan = new Plan;
        $currentPlan->id = 1;
        $currentPlan->name = 'Basic';

        $newPlan = new Plan;
        $newPlan->id = 2;
        $newPlan->name = 'Pro';

        $subscription = new Subscription;
        $subscription->id = 1;
        $subscription->setRelation('tenant', $tenant);
        $subscription->setRelation('plan', $currentPlan);

        $context = new ValidationContext(
            subscription: $subscription,
            plan: $newPlan,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->shouldReceive('canDowngradeTo')
            ->with($tenant, $newPlan)
            ->once()
            ->andReturn(false);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Cannot downgrade from Basic to Pro.');

        $this->validator->validate($context);
    }

    public function test_skips_validation_when_tenant_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: new Plan,
            tenant: null,
        );

        $this->subscriptionService
            ->shouldNotReceive('canDowngradeTo');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_skips_validation_when_plan_is_null(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: $tenant,
        );

        $this->subscriptionService
            ->shouldNotReceive('canDowngradeTo');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }
}
