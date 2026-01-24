# Usage Limit Enforcement

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `database_schema.md`, `backend_subscription_service.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement usage tracking and limit enforcement for subscription resources (reservations, users, locations, services). Includes middleware for automatic limit checking and warning notifications at 80% threshold per PRD REQ-05.

**Architecture Impact**: Adds usage tracking service and middleware. Modifies existing controllers to enforce limits. Integrates with notification system for warnings.

**Risk Assessment**:
- **Medium**: Usage counter accuracy - must handle concurrent requests
- **Medium**: Performance impact of limit checks on every request
- **Low**: Monthly reset timing - use scheduled job

## Data Layer

### Usage Record Model

**File**: `app/Models/UsageRecord.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $period
 * @property int $reservations_count
 * @property int $reservations_limit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Tenant $tenant
 */
final class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'period',
        'reservations_count',
        'reservations_limit',
    ];

    protected function casts(): array
    {
        return [
            'reservations_count' => 'integer',
            'reservations_limit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the current period string (YYYY-MM).
     */
    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
```

### Repository

**File**: `app/Contracts/Repositories/UsageRecordRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Tenant;
use App\Models\UsageRecord;

interface UsageRecordRepository
{
    public function findOrCreateForPeriod(Tenant $tenant, string $period): UsageRecord;

    public function incrementReservations(Tenant $tenant): UsageRecord;

    public function decrementReservations(Tenant $tenant): UsageRecord;

    public function getCurrentUsage(Tenant $tenant): UsageRecord;

    public function recalculateFromDatabase(Tenant $tenant, string $period): UsageRecord;
}
```

**File**: `app/Repositories/Eloquent/EloquentUsageRecordRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\DB;

final class EloquentUsageRecordRepository implements UsageRecordRepository
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
    ) {}

    public function findOrCreateForPeriod(Tenant $tenant, string $period): UsageRecord
    {
        return UsageRecord::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'period' => $period,
            ],
            [
                'reservations_count' => 0,
                'reservations_limit' => $this->subscriptionService->getLimit($tenant, 'reservations_per_month'),
            ]
        );
    }

    public function incrementReservations(Tenant $tenant): UsageRecord
    {
        $period = UsageRecord::currentPeriod();

        return DB::transaction(function () use ($tenant, $period): UsageRecord {
            $record = $this->findOrCreateForPeriod($tenant, $period);

            $record->increment('reservations_count');

            return $record->fresh();
        });
    }

    public function decrementReservations(Tenant $tenant): UsageRecord
    {
        $period = UsageRecord::currentPeriod();

        return DB::transaction(function () use ($tenant, $period): UsageRecord {
            $record = $this->findOrCreateForPeriod($tenant, $period);

            if ($record->reservations_count > 0) {
                $record->decrement('reservations_count');
            }

            return $record->fresh();
        });
    }

    public function getCurrentUsage(Tenant $tenant): UsageRecord
    {
        return $this->findOrCreateForPeriod($tenant, UsageRecord::currentPeriod());
    }

    public function recalculateFromDatabase(Tenant $tenant, string $period): UsageRecord
    {
        $startOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        $count = Appointment::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $record = $this->findOrCreateForPeriod($tenant, $period);
        $record->update(['reservations_count' => $count]);

        return $record->fresh();
    }
}
```

## Component Architecture

### Usage Limit Service

**File**: `app/Services/Subscription/UsageLimitService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\UsageRecordRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Contracts\Services\UsageLimitServiceContract;
use App\Models\Tenant;

final class UsageLimitService implements UsageLimitServiceContract
{
    private const WARNING_THRESHOLD = 0.8; // 80%

    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageRecordRepository $usageRecords,
    ) {}

    /**
     * Check if tenant can create a new reservation.
     */
    public function canCreateReservation(Tenant $tenant): bool
    {
        if ($this->subscriptionService->isUnlimited($tenant, 'reservations_per_month')) {
            return true;
        }

        $usage = $this->usageRecords->getCurrentUsage($tenant);
        $limit = $this->subscriptionService->getLimit($tenant, 'reservations_per_month');

        return $usage->reservations_count < $limit;
    }

    /**
     * Check if tenant can add a new user (staff).
     */
    public function canAddUser(Tenant $tenant): bool
    {
        if ($this->subscriptionService->isUnlimited($tenant, 'users')) {
            return true;
        }

        $currentCount = $tenant->users()->count();
        $limit = $this->subscriptionService->getLimit($tenant, 'users');

        return $currentCount < $limit;
    }

    /**
     * Check if tenant can add a new service.
     */
    public function canAddService(Tenant $tenant): bool
    {
        if ($this->subscriptionService->isUnlimited($tenant, 'services')) {
            return true;
        }

        $currentCount = $tenant->services()->count();
        $limit = $this->subscriptionService->getLimit($tenant, 'services');

        return $currentCount < $limit;
    }

    /**
     * Get usage percentage for reservations.
     */
    public function getReservationUsagePercentage(Tenant $tenant): float
    {
        if ($this->subscriptionService->isUnlimited($tenant, 'reservations_per_month')) {
            return 0.0;
        }

        $usage = $this->usageRecords->getCurrentUsage($tenant);
        $limit = $this->subscriptionService->getLimit($tenant, 'reservations_per_month');

        if ($limit === 0) {
            return 100.0;
        }

        return min(100.0, ($usage->reservations_count / $limit) * 100);
    }

    /**
     * Check if tenant should receive usage warning (at 80%).
     */
    public function shouldWarnAboutUsage(Tenant $tenant): bool
    {
        $percentage = $this->getReservationUsagePercentage($tenant);

        return $percentage >= (self::WARNING_THRESHOLD * 100) && $percentage < 100;
    }

    /**
     * Get all usage metrics for dashboard display.
     *
     * @return array<string, array{current: int, limit: int|string, percentage: float}>
     */
    public function getUsageMetrics(Tenant $tenant): array
    {
        $usage = $this->usageRecords->getCurrentUsage($tenant);

        $reservationsLimit = $this->subscriptionService->getLimit($tenant, 'reservations_per_month');
        $usersLimit = $this->subscriptionService->getLimit($tenant, 'users');
        $servicesLimit = $this->subscriptionService->getLimit($tenant, 'services');

        return [
            'reservations' => [
                'current' => $usage->reservations_count,
                'limit' => $reservationsLimit === -1 ? 'unlimited' : $reservationsLimit,
                'percentage' => $reservationsLimit === -1 ? 0 : min(100, ($usage->reservations_count / $reservationsLimit) * 100),
            ],
            'users' => [
                'current' => $tenant->users()->count(),
                'limit' => $usersLimit === -1 ? 'unlimited' : $usersLimit,
                'percentage' => $usersLimit === -1 ? 0 : min(100, ($tenant->users()->count() / $usersLimit) * 100),
            ],
            'services' => [
                'current' => $tenant->services()->count(),
                'limit' => $servicesLimit === -1 ? 'unlimited' : $servicesLimit,
                'percentage' => $servicesLimit === -1 ? 0 : min(100, ($tenant->services()->count() / $servicesLimit) * 100),
            ],
        ];
    }

    /**
     * Record that a reservation was created.
     */
    public function recordReservationCreated(Tenant $tenant): void
    {
        $this->usageRecords->incrementReservations($tenant);
    }

    /**
     * Record that a reservation was deleted/cancelled.
     */
    public function recordReservationDeleted(Tenant $tenant): void
    {
        $this->usageRecords->decrementReservations($tenant);
    }
}
```

### Service Contract

**File**: `app/Contracts/Services/UsageLimitServiceContract.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;

interface UsageLimitServiceContract
{
    public function canCreateReservation(Tenant $tenant): bool;

    public function canAddUser(Tenant $tenant): bool;

    public function canAddService(Tenant $tenant): bool;

    public function getReservationUsagePercentage(Tenant $tenant): float;

    public function shouldWarnAboutUsage(Tenant $tenant): bool;

    public function getUsageMetrics(Tenant $tenant): array;

    public function recordReservationCreated(Tenant $tenant): void;

    public function recordReservationDeleted(Tenant $tenant): void;
}
```

### Middleware

**File**: `app/Http/Middleware/CheckReservationLimit.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckReservationLimit
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
        private readonly TenantContextService $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (!$tenant) {
            return $next($request);
        }

        if (!$this->usageLimitService->canCreateReservation($tenant)) {
            return response()->json([
                'error' => 'reservation_limit_exceeded',
                'message' => 'You have reached your monthly reservation limit. Please upgrade your plan to create more reservations.',
                'upgrade_url' => route('subscription.plans'),
            ], 403);
        }

        return $next($request);
    }
}
```

**File**: `app/Http/Middleware/CheckUserLimit.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckUserLimit
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
        private readonly TenantContextService $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (!$tenant) {
            return $next($request);
        }

        if (!$this->usageLimitService->canAddUser($tenant)) {
            return response()->json([
                'error' => 'user_limit_exceeded',
                'message' => 'You have reached your user limit. Please upgrade your plan to add more team members.',
                'upgrade_url' => route('subscription.plans'),
            ], 403);
        }

        return $next($request);
    }
}
```

**File**: `app/Http/Middleware/CheckServiceLimit.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckServiceLimit
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
        private readonly TenantContextService $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (!$tenant) {
            return $next($request);
        }

        if (!$this->usageLimitService->canAddService($tenant)) {
            return response()->json([
                'error' => 'service_limit_exceeded',
                'message' => 'You have reached your service limit. Please upgrade your plan to add more services.',
                'upgrade_url' => route('subscription.plans'),
            ], 403);
        }

        return $next($request);
    }
}
```

### Kernel Registration

**File**: `bootstrap/app.php` - Register middleware aliases

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'check.reservation.limit' => \App\Http\Middleware\CheckReservationLimit::class,
        'check.user.limit' => \App\Http\Middleware\CheckUserLimit::class,
        'check.service.limit' => \App\Http\Middleware\CheckServiceLimit::class,
    ]);
})
```

### Observer for Usage Tracking

**File**: `app/Observers/AppointmentObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Models\Appointment;

final class AppointmentObserver
{
    public function __construct(
        private readonly UsageLimitServiceContract $usageLimitService,
    ) {}

    public function created(Appointment $appointment): void
    {
        $this->usageLimitService->recordReservationCreated($appointment->tenant);
    }

    public function deleted(Appointment $appointment): void
    {
        $this->usageLimitService->recordReservationDeleted($appointment->tenant);
    }
}
```

### Scheduled Job for Usage Warnings

**File**: `app/Jobs/CheckUsageWarningsJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\UsageLimitServiceContract;
use App\Models\Tenant;
use App\Notifications\UsageWarningNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckUsageWarningsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(UsageLimitServiceContract $usageLimitService): void
    {
        Tenant::with('owner')
            ->whereHas('subscriptions', static fn ($q) => $q->whereIn('stripe_status', ['active', 'trialing']))
            ->chunk(100, static function ($tenants) use ($usageLimitService): void {
                foreach ($tenants as $tenant) {
                    if ($usageLimitService->shouldWarnAboutUsage($tenant)) {
                        $owner = $tenant->owner;
                        if ($owner) {
                            $owner->notify(new UsageWarningNotification($tenant));
                        }
                    }
                }
            });
    }
}
```

## API Specification

### Usage Metrics Endpoint

```yaml
/api/subscription/usage:
  get:
    summary: Get current usage metrics
    security:
      - bearerAuth: []
    responses:
      200:
        description: Usage metrics
        content:
          application/json:
            schema:
              type: object
              properties:
                reservations:
                  type: object
                  properties:
                    current:
                      type: integer
                    limit:
                      oneOf:
                        - type: integer
                        - type: string
                          enum: [unlimited]
                    percentage:
                      type: number
                users:
                  type: object
                  properties:
                    current:
                      type: integer
                    limit:
                      oneOf:
                        - type: integer
                        - type: string
                    percentage:
                      type: number
                services:
                  type: object
                  properties:
                    current:
                      type: integer
                    limit:
                      oneOf:
                        - type: integer
                        - type: string
                    percentage:
                      type: number
```

## Route Modifications

### Apply Middleware to Existing Routes

```php
// routes/api.php

// Appointments - check reservation limit on creation
Route::post('/appointments', [AppointmentController::class, 'store'])
    ->middleware('check.reservation.limit');

// Staff - check user limit on creation
Route::post('/staff', [StaffController::class, 'store'])
    ->middleware('check.user.limit');

// Services - check service limit on creation
Route::post('/services', [ServiceController::class, 'store'])
    ->middleware('check.service.limit');
```

## Testing Strategy

### E2E Test
- `TestUsageLimitEnforcement` covering limit reached scenario, warning at 80%
- Verify: Creation blocked at limit, warning triggered at 80%, metrics accurate

### Manual Verification
- Create reservations until 80% warning
- Continue until limit reached
- Verify error response and upgrade prompt

## Implementation Steps

1. **Small** - Create UsageRecord model with PHPDoc annotations
2. **Small** - Create UsageRecordRepository contract and implementation
3. **Medium** - Create UsageLimitService with all limit checking methods
4. **Small** - Create UsageLimitServiceContract interface
5. **Medium** - Create CheckReservationLimit middleware
6. **Small** - Create CheckUserLimit middleware
7. **Small** - Create CheckServiceLimit middleware
8. **Small** - Register middleware aliases in bootstrap/app.php
9. **Medium** - Create AppointmentObserver for usage tracking
10. **Small** - Register observer in AppServiceProvider
11. **Medium** - Create CheckUsageWarningsJob for scheduled warnings
12. **Small** - Schedule job in routes/console.php
13. **Medium** - Apply middleware to existing routes
14. **Small** - Create UsageController with metrics endpoint
15. **Small** - Register service bindings
16. **Medium** - Write unit tests for UsageLimitService
17. **Medium** - Write feature tests for middleware
18. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `database_schema.md`, `backend_subscription_service.md`
- **Blocks**: `backend_feature_gating.md`, `frontend_subscription_dashboard.md`
- **Parallel work**: Can work alongside `backend_billing_invoicing.md`
