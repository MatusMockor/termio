<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentUsageRecordRepository implements UsageRecordRepository
{
    private const int DEFAULT_RESERVATION_LIMIT = 50;

    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
    ) {}

    /**
     * Find or create a usage record for the given tenant and period.
     */
    public function findOrCreateForPeriod(Tenant $tenant, string $period): UsageRecord
    {
        return UsageRecord::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'period' => $period,
            ],
            [
                'reservations_count' => 0,
                'reservations_limit' => $this->getReservationLimit($tenant),
            ]
        );
    }

    /**
     * Get the reservation limit for a tenant, with fallback to default.
     */
    private function getReservationLimit(Tenant $tenant): int
    {
        try {
            return $this->subscriptionService->getLimit($tenant, 'reservations_per_month');
        } catch (RuntimeException) {
            // Fallback to default limit if subscription system is not configured
            return self::DEFAULT_RESERVATION_LIMIT;
        }
    }

    /**
     * Increment the reservations count for the current period.
     * Uses database locking to handle concurrent requests.
     */
    public function incrementReservations(Tenant $tenant): UsageRecord
    {
        $period = $this->getCurrentPeriod();

        return DB::transaction(function () use ($tenant, $period): UsageRecord {
            $record = $this->findOrCreateForPeriod($tenant, $period);

            UsageRecord::where('id', $record->id)
                ->lockForUpdate()
                ->increment('reservations_count');

            /** @var UsageRecord $freshRecord */
            $freshRecord = $record->fresh();

            return $freshRecord;
        });
    }

    /**
     * Decrement the reservations count for the current period.
     * Uses database locking to handle concurrent requests.
     */
    public function decrementReservations(Tenant $tenant): UsageRecord
    {
        $period = $this->getCurrentPeriod();

        return DB::transaction(function () use ($tenant, $period): UsageRecord {
            $record = $this->findOrCreateForPeriod($tenant, $period);

            if ($record->reservations_count > 0) {
                UsageRecord::where('id', $record->id)
                    ->lockForUpdate()
                    ->decrement('reservations_count');
            }

            /** @var UsageRecord $freshRecord */
            $freshRecord = $record->fresh();

            return $freshRecord;
        });
    }

    /**
     * Get the current usage record for a tenant.
     */
    public function getCurrentUsage(Tenant $tenant): UsageRecord
    {
        return $this->findOrCreateForPeriod($tenant, $this->getCurrentPeriod());
    }

    /**
     * Recalculate usage from the database for the given period.
     */
    public function recalculateFromDatabase(Tenant $tenant, string $period): UsageRecord
    {
        /** @var Carbon $parsedDate */
        $parsedDate = Carbon::createFromFormat('Y-m', $period);
        $startOfMonth = $parsedDate->copy()->startOfMonth();
        $endOfMonth = $parsedDate->copy()->endOfMonth();

        $count = Appointment::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $record = $this->findOrCreateForPeriod($tenant, $period);
        $record->update(['reservations_count' => $count]);

        /** @var UsageRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $freshRecord;
    }

    /**
     * Get the current period string (YYYY-MM).
     */
    private function getCurrentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
