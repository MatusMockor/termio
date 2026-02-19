<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Services\Validation\Validators\PlanExistsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlanExistsValidatorTest extends TestCase
{
    use RefreshDatabase;

    private PlanExistsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PlanExistsValidator;
    }

    public function test_passes_when_plan_exists(): void
    {
        $plan = Plan::factory()->create();
        $plan->id = 1;

        $context = new ValidationContext(
            subscription: null,
            plan: $plan,
            tenant: null,
            planId: 1,
        );

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_plan_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: null,
            planId: 456,
        );

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Plan with ID 456 not found.');

        $this->validator->validate($context);
    }

    public function test_throws_with_zero_id_when_plan_id_not_provided(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: null,
        );

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Plan with ID 0 not found.');

        $this->validator->validate($context);
    }
}
