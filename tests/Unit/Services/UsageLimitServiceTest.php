<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Enums\UsageResource;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Subscription\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class UsageLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageLimitService $service;

    private SubscriptionServiceContract&MockObject $subscriptionService;

    private UsageRecordRepository&MockObject $usageRecords;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = $this->createMock(SubscriptionServiceContract::class);
        $this->usageRecords = $this->createMock(UsageRecordRepository::class);

        $this->service = new UsageLimitService(
            $this->subscriptionService,
            $this->usageRecords
        );
    }

    public function test_can_create_reservation_returns_true_when_under_limit(): void
    {
        $tenant = Tenant::factory()->create();

        $usageRecord = UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'reservations_count' => 10,
            'reservations_limit' => 100,
        ]);

        $this->subscriptionService->method('isUnlimited')->willReturn(false);
        $this->subscriptionService->method('getLimit')->willReturn(100);
        $this->usageRecords->method('getCurrentUsage')->willReturn($usageRecord);

        $result = $this->service->canUseResource($tenant, UsageResource::Reservations);

        $this->assertTrue($result);
    }

    public function test_can_create_reservation_returns_false_when_at_limit(): void
    {
        $tenant = Tenant::factory()->create();

        $usageRecord = UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'reservations_count' => 100,
            'reservations_limit' => 100,
        ]);

        $this->subscriptionService->method('isUnlimited')->willReturn(false);
        $this->subscriptionService->method('getLimit')->willReturn(100);
        $this->usageRecords->method('getCurrentUsage')->willReturn($usageRecord);

        $result = $this->service->canUseResource($tenant, UsageResource::Reservations);

        $this->assertFalse($result);
    }

    public function test_can_create_reservation_returns_true_when_unlimited(): void
    {
        $tenant = Tenant::factory()->create();

        $this->subscriptionService->method('isUnlimited')->willReturn(true);

        $result = $this->service->canUseResource($tenant, UsageResource::Reservations);

        $this->assertTrue($result);
    }

    public function test_record_reservation_created_increments_count(): void
    {
        $tenant = Tenant::factory()->create();

        $usageRecord = UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'reservations_count' => 10,
            'reservations_limit' => 100,
        ]);

        $this->usageRecords->expects($this->once())
            ->method('incrementReservations')
            ->with($tenant)
            ->willReturn($usageRecord);

        $this->service->recordReservationCreated($tenant);
    }

    public function test_record_reservation_deleted_decrements_count(): void
    {
        $tenant = Tenant::factory()->create();

        $usageRecord = UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'reservations_count' => 10,
            'reservations_limit' => 100,
        ]);

        $this->usageRecords->expects($this->once())
            ->method('decrementReservations')
            ->with($tenant)
            ->willReturn($usageRecord);

        $this->service->recordReservationDeleted($tenant);
    }
}
