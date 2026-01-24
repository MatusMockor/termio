<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Subscription\UsageLimitService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class UsageLimitServiceTest extends TestCase
{
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
        $tenant = new Tenant;
        $tenant->id = 1;

        $usageRecord = new UsageRecord;
        $usageRecord->reservations_count = 10;
        $usageRecord->reservations_limit = 100;

        $this->subscriptionService->method('isUnlimited')->willReturn(false);
        $this->subscriptionService->method('getLimit')->willReturn(100);
        $this->usageRecords->method('getCurrentUsage')->willReturn($usageRecord);

        $result = $this->service->canCreateReservation($tenant);

        $this->assertTrue($result);
    }

    public function test_can_create_reservation_returns_false_when_at_limit(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $usageRecord = new UsageRecord;
        $usageRecord->reservations_count = 100;
        $usageRecord->reservations_limit = 100;

        $this->subscriptionService->method('isUnlimited')->willReturn(false);
        $this->subscriptionService->method('getLimit')->willReturn(100);
        $this->usageRecords->method('getCurrentUsage')->willReturn($usageRecord);

        $result = $this->service->canCreateReservation($tenant);

        $this->assertFalse($result);
    }

    public function test_can_create_reservation_returns_true_when_unlimited(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $this->subscriptionService->method('isUnlimited')->willReturn(true);

        $result = $this->service->canCreateReservation($tenant);

        $this->assertTrue($result);
    }

    public function test_record_reservation_created_increments_count(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $usageRecord = new UsageRecord;
        $usageRecord->reservations_count = 10;
        $usageRecord->reservations_limit = 100;

        $this->usageRecords->expects($this->once())
            ->method('incrementReservations')
            ->with($tenant)
            ->willReturn($usageRecord);

        $this->service->recordReservationCreated($tenant);
    }

    public function test_record_reservation_deleted_decrements_count(): void
    {
        $tenant = new Tenant;
        $tenant->id = 1;

        $usageRecord = new UsageRecord;
        $usageRecord->reservations_count = 10;
        $usageRecord->reservations_limit = 100;

        $this->usageRecords->expects($this->once())
            ->method('decrementReservations')
            ->with($tenant)
            ->willReturn($usageRecord);

        $this->service->recordReservationDeleted($tenant);
    }
}
