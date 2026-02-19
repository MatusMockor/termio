<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Contracts\Services\UsageValidationServiceContract;
use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Validation\Validators\UsageLimitsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class UsageLimitsValidatorTest extends TestCase
{
    use RefreshDatabase;

    private UsageLimitsValidator $validator;

    private UsageValidationServiceContract&MockObject $usageValidation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usageValidation = $this->createMock(UsageValidationServiceContract::class);
        $this->validator = new UsageLimitsValidator($this->usageValidation);
    }

    public function test_passes_when_no_violations(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;

        $plan = Plan::factory()->create();
        $plan->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: $plan,
            tenant: $tenant,
        );

        $this->usageValidation
            ->expects($this->once())
            ->method('checkLimitViolations')
            ->with($tenant, $plan)
            ->willReturn([]);

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_usage_exceeds_limits(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->id = 1;

        $plan = Plan::factory()->create();
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
            ->expects($this->once())
            ->method('checkLimitViolations')
            ->with($tenant, $plan)
            ->willReturn($violations);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Current usage exceeds new plan limits:');

        $this->validator->validate($context);
    }

    public function test_skips_validation_when_tenant_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: Plan::factory()->create(),
            tenant: null,
        );

        $this->usageValidation
            ->expects($this->never())
            ->method('checkLimitViolations');

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

        $this->usageValidation
            ->expects($this->never())
            ->method('checkLimitViolations');

        $this->validator->validate($context);

        $this->assertTrue(true);
    }
}
