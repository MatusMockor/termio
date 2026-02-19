<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\DTOs\Subscription\ValidationContext;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Services\Validation\Validators\SubscriptionExistsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionExistsValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionExistsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SubscriptionExistsValidator;
    }

    public function test_passes_when_subscription_exists(): void
    {
        $subscription = Subscription::factory()->create();
        $subscription->id = 1;

        $context = new ValidationContext(
            subscription: $subscription,
            plan: null,
            tenant: null,
            subscriptionId: 1,
        );

        $this->validator->validate($context);

        $this->assertTrue(true);
    }

    public function test_throws_when_subscription_is_null(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: null,
            subscriptionId: 123,
        );

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription with ID 123 not found.');

        $this->validator->validate($context);
    }

    public function test_throws_with_zero_id_when_subscription_id_not_provided(): void
    {
        $context = new ValidationContext(
            subscription: null,
            plan: null,
            tenant: null,
        );

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription with ID 0 not found.');

        $this->validator->validate($context);
    }
}
