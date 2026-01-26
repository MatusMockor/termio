<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Contracts\Services\UsageValidationServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Validation\Validators\UsageLimitsValidator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UsageLimitsValidatorTest extends TestCase
{
    private UsageLimitsValidator $validator;

    private UsageValidationServiceContract&MockInterface $usageValidation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usageValidation = Mockery::mock(UsageValidationServiceContract::class);
        $this->validator = new UsageLimitsValidator($this->usageValidation);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_passes_when_no_violations(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $plan = new Plan;
        $plan->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: $plan,
            tenant: $tenant,
        );

        $this->usageValidation
            ->shouldReceive('checkLimitViolations')
            ->with($tenant, $plan)
            ->once()
            ->andReturn([]);

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_usage_exceeds_limits(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $plan = new Plan;
        $plan->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: $plan,
            tenant: $tenant,
        );

        $violations = [
            'users' => ['current' => 10, 'limit' => 5],
            'services' => ['current' => 20, 'limit' => 10],
        ];

        $this->usageValidation
            ->shouldReceive('checkLimitViolations')
            ->with($tenant, $plan)
            ->once()
            ->andReturn($violations);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Current usage exceeds new plan limits:');

        $this->validator->validate($context);
    }

    public function test_skips_validation_when_tenant_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: new Plan,
            tenant: null,
        );

        $this->usageValidation
            ->shouldNotReceive('checkLimitViolations');

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

        $this->usageValidation
            ->shouldNotReceive('checkLimitViolations');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }
}
