<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Tenant;
use App\Models\UsageRecord;

interface UsageRecordRepository
{
    /**
     * Find or create a usage record for the given tenant and period.
     */
    public function findOrCreateForPeriod(Tenant $tenant, string $period): UsageRecord;

    /**
     * Increment the reservations count for the current period.
     */
    public function incrementReservations(Tenant $tenant): UsageRecord;

    /**
     * Decrement the reservations count for the current period.
     */
    public function decrementReservations(Tenant $tenant): UsageRecord;

    /**
     * Get the current usage record for a tenant.
     */
    public function getCurrentUsage(Tenant $tenant): UsageRecord;

    /**
     * Recalculate usage from the database for the given period.
     */
    public function recalculateFromDatabase(Tenant $tenant, string $period): UsageRecord;
}
